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
 * Synchronization handler that collects the synchronization result to local variables for
 * later processing.
 */

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell;

use Sabre\VObject\Component\VCard;
use MStilkerich\CardDavClient\AddressbookCollection;
use MStilkerich\CardDavClient\Services\SyncHandler;

class ShellSyncHandlerCollectChanges implements SyncHandler
{
    /** @var array */
    private $changedCards = [];

    /** @var array */
    private $deletedCards = [];

    public function addressObjectChanged(string $uri, string $etag, ?VCard $card): void
    {
        if (!isset($card)) {
            Shell::$logger->error("Card $uri could not be retrieved / parsed");
            return;
        }

        $uid = (string) $card->UID;
        Shell::$logger->debug("Existing object: $uri ($uid)");
        $this->changedCards[$uid] = [
            'uri'   => $uri,
            'etag'  => $etag,
            'vcard' => $card
        ];
    }

    public function addressObjectDeleted(string $uri): void
    {
        Shell::$logger->error("Deleted object: $uri not expected with this Sync Handler");
        $this->deletedCards[] = $uri;
    }

    /**
     * This sync handler is meant to collect all the changed cards in an addressbook, which we
     * do by emulating an empty/unsynchronized local cache.
     */
    public function getExistingVCardETags(): array
    {
        return [];
    }

    public function getCardByUID(string $uid): ?array
    {
        return $this->changedCards[$uid] ?? null;
    }

    public function getChangedCards(): array
    {
        return $this->changedCards;
    }

    public function getRemovedCards(): array
    {
        return $this->deletedCards;
    }

    public function finalizeSync(): void
    {
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
