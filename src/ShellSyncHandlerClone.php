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

/**
 * Synchronization handler that clones the changes of the given addressbook to another addressbook.
 */

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell;

use Sabre\VObject\Component\VCard;
use MStilkerich\CardDavClient\AddressbookCollection;
use MStilkerich\CardDavClient\Services\SyncHandler;

class ShellSyncHandlerClone implements SyncHandler
{
    /** @var AddressbookCollection */
    private $destAbook;

    /** @var ShellSyncHandlerCollectChanges */
    private $destState;

    /**
     * @var bool Whether to only clone / add cards that do not exist in destination (rest left alone)
     */
    private $newOnly;

    public function __construct(
        AddressbookCollection $target,
        ShellSyncHandlerCollectChanges $destState,
        bool $newOnly = false
    ) {
        $this->destAbook = $target;
        $this->destState = $destState;
        $this->newOnly = $newOnly;
    }

    public function addressObjectChanged(string $uri, string $etag, ?VCard $card): void
    {
        if (!isset($card)) {
            Shell::$logger->error("Card $uri could not be retrieved / parsed");
            return;
        }

        $uid = (string) $card->UID;

        $existingCard = $this->destState->getCardByUID($uid);

        if (isset($existingCard)) {
            $fn = $existingCard["vcard"]->FN ?? "<no name>";

            if ($this->newOnly) {
                Shell::$logger->debug("Skip existing card: $uid ($fn)");
            } else {
                Shell::$logger->debug("Overwriting existing card: $uid ($fn)");
                $this->destAbook->updateCard($existingCard["uri"], $card, $existingCard["etag"]);
            }
        } else {
            [ "uri" => $newuri ] = $this->destAbook->createCard($card);

            $fn = $card->FN ?? "<no name>";
            Shell::$logger->debug("Cloned object: $uri ($fn) to $newuri");
        }
    }

    public function addressObjectDeleted(string $uri): void
    {
        Shell::$logger->error("Deleted object: $uri not expected during clone");
    }

    public function getExistingVCardETags(): array
    {
        return [];
    }

    public function finalizeSync(): void
    {
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
