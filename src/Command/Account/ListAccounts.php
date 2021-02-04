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

/**
 * Command that lists all configured CardDAV accounts.
 */
class ListAccounts extends Command
{
    protected $synopsis = 'Lists the available accounts';
    protected $usage = 'Usage: acc:list';
    protected $help = "Lists the available accounts.";
    protected $minArgs = 0;
    protected $maxArgs = 0;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        $shell = Shell::instance();
        foreach ($shell->config["accounts"] as $name => $account) {
            Shell::$logger->info("Account $name ($account)");
        }

        return true;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
