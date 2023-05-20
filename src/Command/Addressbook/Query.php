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
 * Command to perform a server-side addressbook search.
 */
class Query extends Command
{
    protected $synopsis = 'Queries an addressbook for cards matching criteria';
    protected $usage = 'Usage: ab:query <addressbook_id> <filter-condition>';
    protected $help = "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.\n"
        . "<filter-condition>: A filter of the form [!]PROPERTY:[SEARCHPATTERN]";
    protected $minArgs = 2;
    protected $maxArgs = 2;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        assert(count($args) >= 2);
        [ $abookId, $filter ] = $args;

        $ret = false;
        $shell = Shell::instance();

        $abook = $shell->getAddressbookFromId($abookId);
        if (isset($abook)) {
            [ $prop, $filter ] = explode(':', $filter);

            Shell::$logger->info("Query $prop for $filter");

            $cards = $abook->query([$prop => $filter], [ 'FN' ]);

            foreach ($cards as $card) {
                $card = $card["vcard"];
                $fn = $card->FN ?? "<no name>";
                Shell::$logger->info("Found: $fn");
            }

            $ret = true;
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
