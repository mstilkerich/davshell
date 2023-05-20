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

use MStilkerich\CardDavClient\Shell\{Shell,Command};
use MStilkerich\CardDavClient\Account;

/**
 * Command to add new accounts.
 */
class Add extends Command
{
    protected $synopsis = 'Adds an account';
    protected $usage = 'Usage: acc:add <name> <server> <username> <password>';
    protected $help = "Adds a new account to the list of accounts."
        . "name:   An arbitrary (but unique) name that the account is referenced by within this shell.\n"
        . "server: A servername or URI used as the basis for discovering addressbooks in the account.\n"
        . "username: Username used to authenticate with the server.\n"
        . "password: Password used to authenticate with the server.\n";
    protected $minArgs = 4;
    protected $maxArgs = 4;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        $ret = false;
        $shell = Shell::instance();

        assert(count($args) >= 4);
        [ $name, $srv, $usr, $pw ] = $args;

        if (isset($shell->config["accounts"][$name])) {
            Shell::$logger->error("Account named $name already exists!");
        } else {
            $newAccount = new Account($srv, $usr, $pw);
            $shell->config["accounts"][$name] = $newAccount;

            $shell->writeConfig();
            $ret = true;
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
