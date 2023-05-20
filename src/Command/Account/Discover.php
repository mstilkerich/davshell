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

namespace MStilkerich\CardDavClient\Shell\Command\Account;

use MStilkerich\CardDavClient\Services\Discovery;
use MStilkerich\CardDavClient\Shell\{Shell,Command};
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};

/**
 * Abstract class for shell commands.
 */
class Discover extends Command
{
    protected $synopsis = 'Discovers the available addressbooks in a specified CardDAV account';
    protected $usage = 'Usage: acc:discover <accountname>';
    protected $help = "Discovers the available addressbooks in the specified account using the mechanisms\n"
        . "described by RFC6764 (DNS SRV/TXT lookups, /.well-known URI lookup, plus default locations).";
    protected $minArgs = 1;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        $retval = false;
        $shell = Shell::instance();

        assert(count($args) >= 1);
        [$accountName] = $args;
        $account = $shell->config["accounts"][$accountName] ?? null;

        if (isset($account)) {
            $discover = new Discovery();
            $abooks = $discover->discoverAddressbooks($account);

            if (empty($abooks)) {
                Shell::$logger->error("No addressbooks found for account $accountName");
            } else {
                foreach ($abooks as $abook) {
                    Shell::$logger->notice("Found addressbook: $abook");
                }

                $shell->config['addressbooks'][$accountName] = $abooks;
                $shell->writeConfig();
                $retval = true;
            }
        } else {
            Shell::$logger->error("Unknown account $accountName");
        }

        return $retval;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
