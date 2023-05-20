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

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell\Command\Addressbook;

use MStilkerich\CardDavClient\Shell\{Shell,Command};
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};

/**
 * Command to list available addressbooks.
 */
class ListAddrBooks extends Command
{
    protected $synopsis = 'Lists the available addressbooks';
    protected $usage = 'Usage: ab:list [<accountname>]';
    protected $help = "Lists the available addressbooks for the specified account.\n"
        . "If no account is specified, lists the addressbooks for all accounts. The list includes an\n"
        . "identifier for each addressbooks to be used within this shell to reference this addressbook in\n"
        . "operations";
    protected $minArgs = 0;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        $accountName = $args[0] ?? '';
        $shell = Shell::instance();

        $ret = false;

        $accounts = [];
        if (strlen($accountName) > 0) {
            $abooks = $shell->config["addressbooks"][$accountName] ?? null;
            if (isset($abooks)) {
                $accounts = [ $accountName => $abooks ];
                $ret = true;
            } else {
                Shell::$logger->error("Unknown account $accountName");
            }
        } else {
            $accounts = $shell->config["addressbooks"];
            $ret = true;
        }

        foreach ($accounts as $name => $abooks) {
            $id = 0;

            foreach ($abooks as $abook) {
                Shell::$logger->info("$name@$id - $abook");
                ++$id;
            }
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
