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

use MStilkerich\CardDavClient\Shell\{Shell,Command,ShellSyncHandlerCollectChanges};
use MStilkerich\CardDavClient\Services\Sync;

/**
 * Command to synchronize the contents of an addressbook (dumps names to console).
 */
class Synchronize extends Command
{
    protected $synopsis = 'Synchronizes an addressbook to the local cache.';
    protected $usage = 'Usage: ab:sync <addressbook_id> [<sync-token>]';
    protected $help = "addressbook_id: Identifier of the addressbook as provided by the \"addressbooks\" command.\n"
        . "sync-token: Synchronization token that identifies the base state of the synchronization.";
    protected $minArgs = 1;
    protected $maxArgs = 2;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        assert(count($args) >= 1);
        [ $abookId, $syncToken ] = $args;
        $shell = Shell::instance();
        $ret = false;

        $abook = $shell->getAddressbookFromId($abookId);
        if (isset($abook)) {
            $synchandler = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $synctoken = $syncmgr->synchronize($abook, $synchandler, [ ], $syncToken);

            foreach ($synchandler->getChangedCards() as $card) {
                $fn = $card["vcard"]->FN ?? "<no name>";
                Shell::$logger->info("Changed object: {$card["uri"]} ($fn)");
            }

            foreach ($synchandler->getRemovedCards() as $cardUri) {
                Shell::$logger->info("Deleted object: $cardUri");
            }

            Shell::$logger->info("New sync token: $synctoken");

            $ret = true;
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
