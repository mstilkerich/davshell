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
 * Command to list the members of a WebDAV collection.
 */
class ListCollection extends Command
{
    protected $synopsis = 'List contents of a collection';
    protected $usage = 'Usage: ls [<URI>]';
    protected $help = "Lists the children of the given collection. If no collection is specified, lists the\n"
        . "children of the current working collection.\n"
        . "URI: An URI, absolute or relative to the current working collection.";
    protected $minArgs = 0;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        [ $uri ] = $args;
        $shell = Shell::instance();

        $coll = $shell->curColl;
        if (!isset($coll)) {
            Shell::$logger->error("No current working collection selected - use ab:select first.");
            return false;
        }

        if (strlen($uri) > 0) {
            $target = \Sabre\Uri\resolve($coll->getUri(), $uri);
            if ($target[-1] != '/') {
                $target = "$target/";
            }

            try {
                $coll = WebDavResource::createInstance($target, $coll->getAccount());
                if (!($coll instanceof WebDavCollection)) {
                    Shell::$logger->error("$uri: not a collection");
                    return false;
                }
            } catch (\Exception $e) {
                Shell::$logger->error("ls: " . $e->getMessage());
                return false;
            }
        }

        $children = $coll->getChildren();
        foreach ($children as $child) {
            $basename = $child->getBasename();
            Shell::$logger->info("$basename (" . get_class($child) . ")");
        }

        return false;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
