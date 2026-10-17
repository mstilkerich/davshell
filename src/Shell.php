<?php

/*
 * davshell - Simple Shell to interact with (Card)DAV servers
 *
 * Copyright (C) 2020-2026 Michael Stilkerich <ms@mike2k.de>
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

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell;

use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection};
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

/**
 * Simple CardDAV Shell, mainly for debugging the library.
 *
 * @psalm-type AccountName = string
 *
 * @psalm-type ShellConfig = array{
 *   accounts: array<AccountName, Account>,
 *   addressbooks: array<AccountName, list<AddressbookCollection>>,
 * }
 *
 * @psalm-type AccountSerialized = array{
 *   username: string,
 *   password: string,
 *   discoveryUri: string,
 *   baseUrl: string,
 * }
 *
 * @psalm-type AddressbookSerialized = array{
 *   uri: string,
 * }
 *
 * @psalm-type ShellConfigSerialized = array{
 *   accounts: array<AccountName, AccountSerialized>,
 *   addressbooks: array<AccountName, list<AddressbookSerialized>>,
 * }
 *
 * @psalm-type CommandSpec = array{
 *   synopsis: string,
 *   usage: string,
 *   help: string,
 *   callback: callable(string...):bool,
 *   minargs: int,
 * }
 */
final class Shell
{
    private const CONFFILE = ".davshellrc";
    private const HISTFILE = ".davshell_history";

    /** @var LoggerInterface */
    public static $logger;

    /** @var ?Shell */
    public static $instance;

    /** @var ShellConfig Configuration of the shell */
    public $config;

    /** @var ?string Name of the currently selected addressbook */
    public $curABookId;

    /** @var ?WebDavCollection Currently selected collection accessed via selected addressbook */
    public $curColl;

    public static function instance(): Shell
    {
        if (!isset(self::$instance)) {
            self::$instance = new Shell();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $log = new Logger('davshell');
        $handler = new StreamHandler('php://stdout', 'DEBUG');
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
        $httphandler = new StreamHandler('http.log', 'DEBUG', true, 0600);
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

    private function readConfig(): void
    {
        $config = [
            "accounts" => [],
            "addressbooks" => []
        ];

        $cfgstr = file_get_contents(self::CONFFILE);
        if ($cfgstr !== false) {
            /** @var ShellConfigSerialized */
            $config = json_decode($cfgstr, true);
        }

        // convert arrays back to objects
        $accounts = [];
        foreach ($config["accounts"] as $name => $arr) {
            $accounts[$name] = Account::constructFromArray($arr);
        }
        $config["accounts"] = $accounts;

        $accAbooks = [];
        foreach ($config["addressbooks"] as $name => $abooks) {
            if (isset($accounts[$name])) {
                $account = $accounts[$name];
                $accAbooks["$name"] = [];

                foreach ($abooks as $abook) {
                    if (($abook["uri"] ?? "") === "") {
                        self::$logger->error("Config contains addressbook without URI");
                    } else {
                        $accAbooks[$name][] = new AddressbookCollection($abook["uri"], $account);
                    }
                }
            } else {
                self::$logger->error("Config contains addressbooks for undefined account $name");
            }
        }
        $config["addressbooks"] = $accAbooks;

        $this->config = $config;
    }

    public function writeConfig(): void
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
        readline_completion_function(Completer::complete(...));

        while ($cmd = readline($this->prompt())) {
            $cmd = trim($cmd, " \f\n\r\t\v\0");
            $tokens = preg_split("/\s+/", $cmd);

            if ($tokens !== false && Command::execCommand($tokens)) {
                readline_add_history($cmd);
            }
        }

        readline_write_history(self::HISTFILE);
    }

    public function getAddressbookFromId(string $abookId): ?AddressBookCollection
    {
        $abook = null;

        if (preg_match("/^(.*)@(\d+)$/", $abookId, $matches)) {
            [, $accountName, $abookIdx] = $matches;
            $abookIdx = intval($abookIdx);

            if (isset($this->config["addressbooks"][$accountName][$abookIdx])) {
                $abook = $this->config["addressbooks"][$accountName][$abookIdx];
            } else {
                self::$logger->error("Invalid addressbook ID $abookId");
            }
        } else {
            self::$logger->error("Invalid addressbook ID $abookId");
        }

        return $abook;
    }

    /**
     * Returns the value of a property of a VCard as string.
     *
     * If the VCard contains multiple instances of the property, the value of the first one is returned.
     *
     * @return ?string The property value, or null if the VCard does not contain the property.
     */
    public static function getCardProperty(VCard $card, string $propName): ?string
    {
        // explicit call of the magic getter, as psalm cannot infer the type of a dynamic property fetch
        $prop = $card->__get($propName);
        return ($prop instanceof Property) ? (string) $prop : null;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
