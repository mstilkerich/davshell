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

namespace MStilkerich\CardDavClient\Shell\Command;

use MStilkerich\CardDavClient\Shell\{Shell,Command};
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};

/**
 * Command to list available commands or print help on specific command.
 */
class Help extends Command
{
    protected $synopsis = 'Lists available commands or displays help on a specific command';
    protected $usage = 'Usage: help [<command>]';
    protected $help = "If no command is specified, prints a list of available commands,\n"
        . "otherwise prints help on the specified command.";
    protected $minArgs = 0;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        $ret = false;
        $command = $args[0] ?? '';

        if (strlen($command) > 0) {
            if (isset(self::COMMAND_MAP[$command])) {
                $cmdClass = self::COMMAND_MAP[$command];
                $cmd = new $cmdClass();

                Shell::$logger->info("$command - " . $cmd->synopsis());
                Shell::$logger->info($cmd->usage());
                Shell::$logger->info($cmd->help());
                $ret = true;
            } else {
                Shell::$logger->error("Unknown command: $command");
            }
        } else {
            foreach (self::COMMAND_MAP as $command => $commandClass) {
                $cmd = new $commandClass();
                Shell::$logger->info("$command: " . $cmd->synopsis());
                $ret = true;
            }
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
