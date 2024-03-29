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

use MStilkerich\CardDavClient\Services\Sync;
use MStilkerich\CardDavClient\Shell\{Shell,Command,ShellSyncHandlerCollectChanges};
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config, WebDavCollection, WebDavResource};

/**
 * Command to delete all cards in an addressbook.
 */
class Sweep extends Command
{
    protected $synopsis = 'Deletes all address objects of the given addressbook';
    protected $usage = 'Usage: ab:sweep <addressbook_id>';
    protected $help = "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.";
    protected $minArgs = 1;
    protected $maxArgs = 1;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        assert(count($args) >= 1);
        [ $abookId ] = $args;
        $shell = Shell::instance();
        $ret = false;

        $abook = $shell->getAddressbookFromId($abookId);
        if (isset($abook)) {
            $synchandler = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $syncmgr->synchronize($abook, $synchandler);

            foreach ($synchandler->getChangedCards() as $card) {
                $uri = $card["uri"];
                Shell::$logger->info("Deleting card $uri");
                $abook->deleteCard($uri);
            }

            $ret = true;
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
