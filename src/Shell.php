<?php

/*
 * davshell - Simple Shell to interact with (Card)DAV servers
 *
 * Copyright (C) 2020 Michael Stilkerich <ms@mike2k.de>
 *
 * This file is part of davshell.
 *
 * davshell is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * davshell is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with davshell.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Simple CardDAV Shell, mainly for debugging the library.
 */

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell;

use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};
use MStilkerich\CardDavClient\Services\{Discovery, Sync};
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class Shell
{
    private const CONFFILE = ".davshellrc";
    private const HISTFILE = ".davshell_history";

    private const COMMANDS = [
        'help' => [
            'synopsis' => 'Lists available commands or displays help on a specific command',
            'usage'    => 'Usage: discover <accountname>',
            'help'     => "If no command is specified, prints a list of available commands,\n"
                . "otherwise prints help on the specified command.",
            'callback' => 'showHelp',
            'minargs'  => 0,
        ],
        'discover' => [
            'synopsis' => 'Discovers the available addressbooks in a specified CardDAV account',
            'usage'    => 'Usage: discover <accountname>',
            'help'     => "Discovers the available addressbooks in the specified account using the mechanisms\n"
                . "described by RFC6764 (DNS SRV/TXT lookups, /.well-known URI lookup, plus default locations).",
            'callback' => 'discoverAddressbooks',
            'minargs'  => 1,
        ],
        'accounts' => [
            'synopsis' => 'Lists the available accounts',
            'usage'    => 'Usage: accounts',
            'help'     => "Lists the available accounts.",
            'callback' => 'listAccounts',
            'minargs'  => 0,
        ],
        'add_account' => [
            'synopsis' => 'Adds an account',
            'usage'    => 'Usage: add_account <name> <server> <username> <password>',
            'help'     => "Adds a new account to the list of accounts."
                . "name:   An arbitrary (but unique) name that the account is referenced by within this shell.\n"
                . "server: A servername or URI used as the basis for discovering addressbooks in the account.\n"
                . "username: Username used to authenticate with the server.\n"
                . "password: Password used to authenticate with the server.\n",
            'callback' => 'addAccount',
            'minargs'  => 4,
        ],
        'addressbooks' => [
            'synopsis' => 'Lists the available addressbooks',
            'usage'    => 'Usage: accounts [<accountname>]',
            'help'     => "Lists the available addressbooks for the specified account.\n"
                . "If no account is specified, lists the addressbooks for all accounts. The list includes an\n"
                . "identifier for each addressbooks to be used within this shell to reference this addressbook in\n"
                . "operations",
            'callback' => 'listAddressbooks',
            'minargs'  => 0,
        ],
        'cd' => [
            'synopsis' => 'Change the current working collection',
            'usage'    => 'Usage: cd [<URI>]',
            'help'     => "Changes the currently selected working collection, which is used by some commands if no\n"
                . "collection is specified. If no URI is given, changes to the working addressbook's collection.\n"
                . "URI: An URI, absolute or relative to the current working collection.",
            'callback' => 'changeCollection',
            'minargs'  => 0,
        ],
        'ls' => [
            'synopsis' => 'List contents of a collection',
            'usage'    => 'Usage: ls [<URI>]',
            'help'     => "Lists the children of the given collection. If no collection is specified, lists the\n"
                . "children of the current working collection.\n"
                . "URI: An URI, absolute or relative to the current working collection.",
            'callback' => 'listChildren',
            'minargs'  => 0,
        ],
        'cat' => [
            'synopsis' => 'List content of a resource',
            'usage'    => 'Usage: cat <URI>',
            'help'     => "Lists the content of the given resource.\n"
                . "URI: An URI, absolute or relative to the current working collection.",
            'callback' => 'catResource',
            'minargs'  => 1,
        ],
        'chabook' => [
            'synopsis' => 'Change the currently selected working addressbook',
            'usage'    => 'Usage: chabook <addressbook_id>',
            'help'     => "Changes the currently selected working addressbook, which is used by some commands if no\n"
                . "addressbook is specified.\n"
                . "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.",
            'callback' => 'changeAddressbook',
            'minargs'  => 1,
        ],
        'show_addressbook' => [
            'synopsis' => 'Shows detailed information on the given addressbook.',
            'usage'    => 'Usage: show_addressbook [<addressbook_id>]',
            'help'     => "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.",
            'callback' => 'showAddressbook',
            'minargs'  => 1,
        ],
        'synchronize' => [
            'synopsis' => 'Synchronizes an addressbook to the local cache.',
            'usage'    => 'Usage: synchronize <addressbook_id> [<sync-token>]',
            'help'     => "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.\n"
                . "sync-token: Synchronization token that identifies the base state of the synchronization.",
            'callback' => 'syncAddressbook',
            'minargs'  => 1,
        ],
        'clone' => [
            'synopsis' => 'Clones an addressbook to another addressbok',
            'usage'    => 'Usage: clone <source_addressbook_id> <target_addressbook_id> [-n]',
            'help'     => "source_addressbook_id: Identifier of the source addressbook as provided by the"
                . "\"addressbooks\" command.\n"
                . "target_addressbook_id: Identifier of the target addressbook as provided by the"
                . "\"addressbooks\" command.\n"
                . "Option -n: Only add cards not existing in destination yet, leave the rest alone.",
            'callback' => 'cloneAddressbook',
            'minargs'  => 2,
        ],
        'sweep' => [
            'synopsis' => 'Deletes all address objects of the given addressbook',
            'usage'    => 'Usage: sweep <addressbook_id>',
            'help'     => "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.",
            'callback' => 'sweepAddressbook',
            'minargs'  => 1,
        ],
        'time' => [
            'synopsis' => 'Measures and outputs the time taken by the following command',
            'usage'    => 'Usage: time <command> [<command_args>]',
            'help'     => "",
            'callback' => 'timedExecution',
            'minargs'  => 1,
        ],
    ];

    /** @var array Configuration of the shell */
    private $config;

    /** @var ?string Name of the currently selected addressbook */
    private $curABookId;

    /** @var ?WebDavCollection Currently selected collection accessed via selected addressbook */
    private $curColl;

    /** @var LoggerInterface */
    public static $logger;

    public function __construct()
    {
        $log = new Logger('davshell');
        $handler = new StreamHandler('php://stdout', Logger::DEBUG);
        $handler->setFormatter(new ColoredLineFormatter(
            null,
            "%message% %context% %extra%\n",
            "",   // no date output needed
            true, // allow linebreaks in message
            true  // remove empty context and extra fields (trailing [] [])
        ));
        $log->pushHandler($handler);
        self::$logger = $log;

        $httplog = new Logger('davshell');
        $httphandler = new StreamHandler('http.log', Logger::DEBUG, true, 0600);
        $httphandler->setFormatter(new LineFormatter(
            "[%datetime%] %level_name%: %message% %context% %extra%",
            'Y-m-d H:i:s', // simplified date format
            true, // allow linebreaks in message
            true  // remove empty context and extra fields (trailing [] [])
        ));
        $httplog->pushHandler($httphandler);

        Config::init($log, $httplog);

        $this->readConfig();
    }

    private function listAccounts(string $opt = ""): bool
    {
        foreach ($this->config["accounts"] as $name => $account) {
            self::$logger->info("Account $name ($account)");
        }

        return true;
    }

    private static function commandCompletion(string $word, int $index): array
    {
        // FIXME to be done
        //Get info about the current buffer
        $rl_info = readline_info();

        // Figure out what the entire input is
        $full_input = substr($rl_info['line_buffer'], 0, $rl_info['end']);

        $matches = array();

        // Get all matches based on the entire input buffer
        //foreach (phrases_that_begin_with($full_input) as $phrase) {
            // Only add the end of the input (where this word begins)
            // to the matches array
        //    $matches[] = substr($phrase, $index);
        //}

        return $matches;
    }

    private function addAccount(string $name, string $srv, string $usr, string $pw): bool
    {
        $ret = false;

        if (isset($this->config["accounts"][$name])) {
            self::$logger->error("Account named $name already exists!");
        } else {
            $newAccount = new Account($srv, $usr, $pw);
            $this->config["accounts"][$name] = $newAccount;

            $this->writeConfig();
            $ret = true;
        }

        return $ret;
    }

    private function showHelp(string $command = null): bool
    {
        $ret = false;

        if (isset($command)) {
            if (isset(self::COMMANDS[$command])) {
                self::$logger->info("$command - " . self::COMMANDS[$command]['synopsis']);
                self::$logger->info(self::COMMANDS[$command]['usage']);
                self::$logger->info(self::COMMANDS[$command]['help']);
                $ret = true;
            } else {
                self::$logger->error("Unknown command: $command");
            }
        } else {
            foreach (self::COMMANDS as $command => $commandDesc) {
                self::$logger->info("$command: " . $commandDesc['synopsis']);
            }
        }

        return $ret;
    }

    private function changeCollection(string $uri = null): bool
    {
        if (empty($uri)) {
            $abook = isset($this->curABookId) ? $this->getAddressbookFromId($this->curABookId) : null;
            if (isset($abook)) {
                $uri = $abook->getUriPath();
            } else {
                self::$logger->error("No current addressbook selected - use chabook first.");
                return false;
            }
        }

        if (isset($this->curColl)) {
            $target = \Sabre\Uri\resolve($this->curColl->getUri(), $uri);
            if ($target[-1] != '/') {
                $target = "$target/";
            }

            try {
                $coll = WebDavResource::createInstance($target, $this->curColl->getAccount());
                if ($coll instanceof WebDavCollection) {
                    $this->curColl = $coll;
                    return true;
                } else {
                    self::$logger->error("cd: not a collection");
                    return false;
                }
            } catch (\Exception $e) {
                self::$logger->error("cd: " . $e->getMessage());
                return false;
            }
        } else {
            self::$logger->error("No current addressbook selected - use chabook first.");
        }

        return false;
    }

    private function listChildren(string $uri = null): bool
    {
        $coll = $this->curColl;
        if (!isset($coll)) {
            self::$logger->error("No current working collection selected - use chabook first.");
            return false;
        }

        if (!empty($uri)) {
            $target = \Sabre\Uri\resolve($coll->getUri(), $uri);
            if ($target[-1] != '/') {
                $target = "$target/";
            }

            try {
                $coll = WebDavResource::createInstance($target, $coll->getAccount());
                if (!($coll instanceof WebDavCollection)) {
                    self::$logger->error("$uri: not a collection");
                    return false;
                }
            } catch (\Exception $e) {
                self::$logger->error("ls: " . $e->getMessage());
                return false;
            }
        }

        $children = $coll->getChildren();
        foreach ($children as $child) {
            $basename = $child->getBasename();
            self::$logger->info("$basename (" . get_class($child) . ")");
        }

        return false;
    }

    private function catResource(string $uri): bool
    {
        $coll = $this->curColl;
        if (!isset($coll)) {
            self::$logger->error("No current working collection selected - use chabook first.");
            return false;
        }

        try {
            [ 'body' => $content ] = $coll->downloadResource($uri);
            self::$logger->info($content);
            return true;
        } catch (\Exception $e) {
            self::$logger->error("cat $uri: " . $e->getMessage());
        }

        return false;
    }

    private function changeAddressbook(string $abookId): bool
    {
        $retval = false;

        $abook = $this->getAddressbookFromId($abookId);
        if (isset($abook)) {
            $this->curABookId = $abookId;
            $this->curColl = $abook;
            $retval = $this->changeCollection();
        } else {
            self::$logger->error("Unknown addressbook $abookId");
        }

        return $retval;
    }

    private function discoverAddressbooks(string $accountName): bool
    {
        $retval = false;
        $account = $this->config["accounts"][$accountName] ?? null;

        if (isset($account)) {
            $discover = new Discovery();
            $abooks = $discover->discoverAddressbooks($account);

            if (empty($abooks)) {
                self::$logger->error("No addressbooks found for account $accountName");
            } else {
                foreach ($abooks as $abook) {
                    self::$logger->notice("Found addressbook: $abook");
                }

                $this->config['addressbooks'][$accountName] = $abooks;
                $this->writeConfig();
                $retval = true;
            }
        } else {
            self::$logger->error("Unknown account $accountName");
        }

        return $retval;
    }

    private function listAddressbooks(string $accountName = null): bool
    {
        $ret = false;
        $accounts = [];

        if (isset($accountName)) {
            $abooks = $this->config["addressbooks"][$accountName] ?? null;
            if (isset($abooks)) {
                $accounts = [ $accountName => $abooks ];
                $ret = true;
            } else {
                self::$logger->error("Unknown account $accountName");
            }
        } else {
            $accounts = $this->config["addressbooks"];
            $ret = true;
        }

        foreach ($accounts as $name => $abooks) {
            $id = 0;

            foreach ($abooks as $abook) {
                self::$logger->info("$name@$id - $abook");
                ++$id;
            }
        }

        return $ret;
    }

    private function showAddressbook(string $abookId): bool
    {
        $ret = false;

        $abook = $this->getAddressbookFromId($abookId);
        if (isset($abook)) {
                self::$logger->info($abook->getDetails());
                $ret = true;
        }

        return $ret;
    }

    private function syncAddressbook(string $abookId, string $syncToken = ""): bool
    {
        $ret = false;

        $abook = $this->getAddressbookFromId($abookId);
        if (isset($abook)) {
            $synchandler = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $synctoken = $syncmgr->synchronize($abook, $synchandler, [ ], $syncToken);

            foreach ($synchandler->getChangedCards() as $card) {
                self::$logger->info("Changed object: " . $card["uri"] . " (" . $card["vcard"]->FN . ")");
            }

            foreach ($synchandler->getRemovedCards() as $cardUri) {
                self::$logger->info("Deleted object: $cardUri");
            }

            self::$logger->info("New sync token: $synctoken");

            $ret = true;
        }

        return $ret;
    }

    private function sweepAddressbook(string $abookId): bool
    {
        $ret = false;

        $abook = $this->getAddressbookFromId($abookId);
        if (isset($abook)) {
            $synchandler = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $synctoken = $syncmgr->synchronize($abook, $synchandler);

            foreach ($synchandler->getChangedCards() as $card) {
                $uri = $card["uri"];
                self::$logger->info("Deleting card $uri");
                $abook->deleteCard($uri);
            }

            $ret = true;
        }

        return $ret;
    }

    private function cloneAddressbook(string $srcAbookId, string $targetAbookId, string $opt = ""): bool
    {
        $ret = false;
        $addOnly = ($opt == "-n");

        $src = $this->getAddressbookFromId($srcAbookId);
        $dest = $this->getAddressbookFromId($targetAbookId);

        if (isset($src) && isset($dest)) {
            $destState = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $destSynctoken = $syncmgr->synchronize($dest, $destState);

            $cloneMgr = new ShellSyncHandlerClone($dest, $destState, $addOnly);
            $syncmgr = new Sync();
            $srcSyncToken = $syncmgr->synchronize($src, $cloneMgr);

            $ret = true;
        }

        return $ret;
    }

    private function getAddressbookFromId(string $abookId): ?AddressBookCollection
    {
        if (preg_match("/^(.*)@(\d+)$/", $abookId, $matches)) {
            [, $accountName, $abookIdx] = $matches;
            $abook = $this->config["addressbooks"][$accountName][$abookIdx] ?? null;

            if (!isset($abook)) {
                self::$logger->error("Invalid addressbook ID $abookId");
            }
        } else {
            self::$logger->error("Invalid addressbook ID $abookId");
        }

        return $abook ?? null;
    }

    private function timedExecution(string ...$tokens): bool
    {
        $start = time();
        $ret = $this->execCommand($tokens);
        $duration = time() - $start;

        self::$logger->notice("Execution of command $tokens[0] took $duration seconds");

        return $ret;
    }

    private function readConfig(): void
    {
        $this->config = [];

        $cfgstr = file_get_contents(self::CONFFILE);
        if ($cfgstr !== false) {
            $this->config = json_decode($cfgstr, true);
        }

        if (empty($this->config)) {
            $this->config = [
                "accounts" => [],
                "addressbooks" => []
            ];
        }

        // convert arrays back to objects
        $accounts = [];
        foreach ($this->config["accounts"] as $name => $arr) {
            $accounts[$name] = Account::constructFromArray($arr);
        }
        $this->config["accounts"] = $accounts;

        $accAbooks = [];
        foreach ($this->config["addressbooks"] as $name => $abooks) {
            $account = $accounts[$name] ?? null;
            if (isset($account)) {
                $accAbooks["$name"] = [];

                foreach ($abooks as $abook) {
                    if (empty($abook["uri"])) {
                        self::$logger->error("Config contains addressbook without URI");
                    } else {
                        $accAbooks[$name][] = new AddressbookCollection($abook["uri"], $account);
                    }
                }
            } else {
                self::$logger->error("Config contains addressbooks for undefined account $name");
            }
        }
        $this->config["addressbooks"] = $accAbooks;
    }

    private function writeConfig(): void
    {
        $config = $this->config;

        $cfgstr = json_encode($config, JSON_PRETTY_PRINT);

        if ($cfgstr !== false) {
            file_put_contents(self::CONFFILE, $cfgstr);
            chmod(self::CONFFILE, 0600);
        } else {
            self::$logger->error("Could not serialize config to JSON");
        }
    }

    private function execCommand(array $tokens): bool
    {
        $ret = false;
        $command = array_shift($tokens);

        if (isset(self::COMMANDS[$command])) {
            if (count($tokens) >= self::COMMANDS[$command]['minargs']) {
                try {
                    $ret = call_user_func_array([$this, self::COMMANDS[$command]['callback']], $tokens);
                } catch (\Exception $e) {
                    self::$logger->error("Command raised Exception: " . $e);
                }
            } else {
                self::$logger->error("Too few arguments to $command.");
                self::$logger->info(self::COMMANDS[$command]['usage']);
            }
        } else {
            self::$logger->error("Unknown command $command. Type \"help\" for a list of available commands");
        }

        return $ret;
    }

    private function prompt(): string
    {
        $ret = "";

        if (isset($this->curABookId)) {
            $ret .= $this->curABookId . ':';
        }

        $coll = $this->curColl;
        if (isset($coll)) {
            $ret .= $coll->getUriPath();
            $ret .= ($coll instanceof AddressbookCollection) ? " [A]" : " [C]";
        }

        $ret .= "> ";

        return $ret;
    }

    public function run(): void
    {
        readline_read_history(self::HISTFILE);

        while ($cmd = readline($this->prompt())) {
            $cmd = trim($cmd);
            $tokens = preg_split("/\s+/", $cmd);

            if ($this->execCommand($tokens)) {
                readline_add_history($cmd);
            }
        }

        readline_write_history(self::HISTFILE);
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
