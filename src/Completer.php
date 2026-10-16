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

namespace MStilkerich\CardDavClient\Shell;

/**
 * Tab completion for commands and their arguments, to be registered with readline_completion_function().
 */
final class Completer
{
    /**
     * Callback for readline_completion_function().
     *
     * The readline library splits the line into words at characters different from those used by the shell's
     * tokenizer (e.g. at '@'), and $input only contains the part of the current word after the last such
     * character. Therefore, the current word is determined from the line buffer, and the returned candidates are
     * truncated to the part that corresponds to $input.
     *
     * @param string $input The part of the current word as determined by the readline library.
     * @return list<string> The completion candidates for $input.
     */
    public static function complete(string $input): array
    {
        // prevent GNU readline from falling back to filename completion if we return no candidates
        readline_info('attempted_completion_over', 1);

        $line = readline_info('line_buffer');
        $point = readline_info('point');
        if (!is_string($line) || !is_int($point)) {
            return [];
        }

        $line = substr($line, 0, $point);
        $tokens = preg_split("/\s+/", ltrim($line, " \f\n\r\t\v\0"));
        if ($tokens === false) {
            return [];
        }

        $word = array_pop($tokens);
        $offset = strlen($word) - strlen($input);
        if ($offset < 0 || substr($word, $offset) !== $input) {
            return [];
        }

        $candidates = array_filter(
            self::candidates($tokens, $word),
            fn (string $c): bool => str_starts_with($c, $word)
        );
        $candidates = array_unique(array_map(fn (string $c): string => substr($c, $offset), $candidates));
        sort($candidates);
        return $candidates;
    }

    /**
     * Determines the completion candidates for the word following the given tokens.
     *
     * @param list<string> $tokens The tokens of the command line preceding the word to complete.
     * @param string $word The (partial) word to complete.
     * @return list<string> The completion candidates; may include candidates not matching $word.
     */
    private static function candidates(array $tokens, string $word): array
    {
        if (empty($tokens)) {
            return Command::commandNames();
        }

        $argIdx = count($tokens) - 1;
        $argTypes = Command::forName($tokens[0])?->argTypes() ?? [];

        $cmdLineIdx = array_search(ArgType::CommandLine, $argTypes, true);
        if ($cmdLineIdx !== false && $argIdx >= $cmdLineIdx) {
            return self::candidates(array_slice($tokens, $cmdLineIdx + 1), $word);
        }

        return match ($argTypes[$argIdx] ?? null) {
            ArgType::Command => Command::commandNames(),
            ArgType::Account => self::accountNames(),
            ArgType::Addressbook => self::addressbookIds(),
            ArgType::File => self::files($word),
            ArgType::CommandLine, null => [],
        };
    }

    /**
     * @return list<string> The names of all accounts known to the shell.
     */
    private static function accountNames(): array
    {
        $names = [];
        foreach (array_keys(Shell::instance()->config['accounts']) as $accountName) {
            $names[] = "$accountName"; // numeric names are int keys at runtime
        }
        return $names;
    }

    /**
     * @return list<string> The IDs of all addressbooks known to the shell.
     */
    private static function addressbookIds(): array
    {
        $ids = [];
        foreach (Shell::instance()->config['addressbooks'] as $accountName => $abooks) {
            foreach (array_keys($abooks) as $idx) {
                $ids[] = "$accountName@$idx";
            }
        }
        return $ids;
    }

    /**
     * Lists the entries of the local directory referenced by a partial path.
     *
     * Directories are suffixed with a '/'. If the path matches exactly one directory, its entries are listed as
     * well, so that readline completes the directory without appending a space and the user can continue with
     * completion inside it.
     *
     * @return list<string>
     */
    private static function files(string $word): array
    {
        $matches = array_values(
            array_filter(self::dirEntries($word), fn (string $e): bool => str_starts_with($e, $word))
        );

        if (count($matches) == 1 && str_ends_with($matches[0], '/')) {
            $matches = array_merge($matches, self::dirEntries($matches[0]));
        }

        return $matches;
    }

    /**
     * @param string $path A partial path; the directory part up to the last '/' is listed.
     * @return list<string> The entries of the directory, including the directory part of $path.
     */
    private static function dirEntries(string $path): array
    {
        $pos = strrpos($path, '/');
        $dir = ($pos === false) ? '' : substr($path, 0, $pos + 1);
        $basename = substr($path, strlen($dir));

        $listDir = ($dir === '') ? '.' : $dir;
        $dirEntries = is_dir($listDir) ? scandir($listDir) : false;
        if ($dirEntries === false) {
            return [];
        }

        $entries = [];
        foreach ($dirEntries as $entry) {
            // hidden files are only offered if the user started typing their name with a '.'
            if ($entry == '.' || $entry == '..' || ($entry[0] == '.' && !str_starts_with($basename, '.'))) {
                continue;
            }

            $entry = $dir . $entry;
            $entries[] = is_dir($entry) ? "$entry/" : $entry;
        }

        return $entries;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
