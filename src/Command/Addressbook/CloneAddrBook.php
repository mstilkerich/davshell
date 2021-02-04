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
use MStilkerich\CardDavClient\Shell\{Shell,Command,ShellSyncHandlerCollectChanges,ShellSyncHandlerClone};

/**
 * Command to clone one addressbook's content into another addressbook.
 */
class CloneAddrBook extends Command
{
    protected $synopsis = 'Clones an addressbook to another addressbok';
    protected $usage = 'Usage: ab:clone <source_addressbook_id> <target_addressbook_id> [-n]';
    protected $help = "source_addressbook_id: Identifier of the source addressbook as provided by the"
        . "\"addressbooks\" command.\n"
        . "target_addressbook_id: Identifier of the target addressbook as provided by the"
        . "\"addressbooks\" command.\n"
        . "Option -n: Only add cards not existing in destination yet, leave the rest alone.";
    protected $minArgs = 2;
    protected $maxArgs = 3;

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    public function run(array $args): bool
    {
        [ $srcAbookId, $targetAbookId, $opt ] = $args;
        $shell = Shell::instance();

        $ret = false;
        $addOnly = ($opt == "-n");

        $src = $shell->getAddressbookFromId($srcAbookId);
        $dest = $shell->getAddressbookFromId($targetAbookId);

        if (isset($src) && isset($dest)) {
            $destState = new ShellSyncHandlerCollectChanges();
            $syncmgr = new Sync();
            $destSynctoken = $syncmgr->synchronize($dest, $destState);

            $cloneMgr = new ShellSyncHandlerClone($dest, $destState, $addOnly);
            $syncmgr = new Sync();
            $srcSyncToken = $syncmgr->synchronize($src, $cloneMgr);

            $ret = true;
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
