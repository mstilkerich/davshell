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
use MStilkerich\CardDavClient\{WebDavCollection, WebDavResource};

/**
 * Command changes the currently selected collection of the shell.
 */
class ChangeCollection extends Command
{
    protected $synopsis = 'Change the current working collection';
    protected $usage = 'Usage: cd [<URI>]';
    protected $help = "Changes the currently selected working collection, which is used by some commands if no\n"
        . "collection is specified. If no URI is given, changes to the working addressbook's collection.\n"
        . "URI: An URI, absolute or relative to the current working collection.";
    protected $minArgs = 0;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        [$uri] = $args;
        $shell = Shell::instance();

        if (strlen($uri) == 0) {
            $abook = isset($shell->curABookId) ? $shell->getAddressbookFromId($shell->curABookId) : null;
            if (isset($abook)) {
                $uri = $abook->getUriPath();
            } else {
                Shell::$logger->error("No current addressbook selected - use ab:select first.");
                return false;
            }
        }

        if (isset($shell->curColl)) {
            $target = \Sabre\Uri\resolve($shell->curColl->getUri(), $uri);
            if ($target[-1] != '/') {
                $target = "$target/";
            }

            try {
                $coll = WebDavResource::createInstance($target, $shell->curColl->getAccount());
                if ($coll instanceof WebDavCollection) {
                    $shell->curColl = $coll;
                    return true;
                } else {
                    Shell::$logger->error("cd: not a collection");
                    return false;
                }
            } catch (\Exception $e) {
                Shell::$logger->error("cd: " . $e->getMessage());
                return false;
            }
        } else {
            Shell::$logger->error("No current addressbook selected - use chabook first.");
        }

        return false;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
