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

use Sabre\VObject;
use Sabre\VObject\Component\VCard;
use MStilkerich\CardDavClient\Shell\{Shell,Command};
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};

/**
 * Command to create a VCard in an addressbook from a local file.
 */
class CreateCard extends Command
{
    protected $synopsis = 'Create an address object from a local vcard';
    protected $usage = 'Usage: ab:putcard <filename> [<addressbook_id>]';
    protected $help = "Creates a new resource from a local file in the given or current addressbook.\n"
        . "filename: Absolute or relative to the current working directory."
        . "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.";
    protected $minArgs = 1;
    protected $maxArgs = 2;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        assert(count($args) >= 1);
        [ $filename, $abookId ] = $args;
        $shell = Shell::instance();

        if (strlen($abookId) == 0) {
            $abookId = $shell->curABookId;
        }

        if (isset($abookId)) {
            $abook = $shell->getAddressbookFromId($abookId);
            if (!isset($abook)) {
                Shell::$logger->error("Unknown addressbook $abookId");
                return false;
            }
        } else {
            Shell::$logger->error("Specify addressbook or use ab:select first to set a working collection.");
            return false;
        }

        try {
            $vcard = VObject\Reader::read(fopen($filename, 'r'));
            if (!($vcard instanceof VCard)) {
                Shell::$logger->error("Could not read VCard from $filename");
                return false;
            }

            [ 'uri' => $uri, 'etag' => $etag ] = $abook->createCard($vcard);
            Shell::$logger->info("Created card at $uri with ETag $etag");
            return true;
        } catch (\Exception $e) {
            Shell::$logger->error("put_card $filename to $abookId: " . $e->getMessage());
        }

        return false;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
