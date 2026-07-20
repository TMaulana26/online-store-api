<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PlayHiddenItemCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'play:hidden-item
                            {--A= : Number of steps Up/North}
                            {--B= : Number of steps Right/East}
                            {--C= : Number of steps Down/South}
                            {--D= : Number of steps Left/West}
                            {--reset : Reset the active game session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Solves and plays the Hidden Item game grid navigation path';

    /**
     * The 6x8 layout grid.
     * Row index goes 0 to 5, Column index goes 0 to 7.
     */
    private array $grid = [
        '########',
        '#......#',
        '#.###..#',
        '#...#.##',
        '#X#....#',
        '########',
    ];

    private array $start = [4, 1]; // Row 4, Col 1

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $resetOpt = $this->option('reset');
        $aOpt = $this->option('A');
        $bOpt = $this->option('B');
        $cOpt = $this->option('C');
        $dOpt = $this->option('D');

        if ($resetOpt) {
            $session = $this->getOrCreateSession(true);
            $this->info('Game session reset! A new hidden item has been placed.');
            $this->displayGrid($session['player'], $session['item']);

            return 0;
        }

        $session = $this->getOrCreateSession();

        if ($aOpt !== null || $bOpt !== null || $cOpt !== null || $dOpt !== null) {
            // Validate specific input steps
            if ($aOpt === null || $bOpt === null || $cOpt === null || $dOpt === null) {
                $this->error('To move, you must provide all options: --A, --B, --C, and --D.');

                return 1;
            }

            $A = (int) $aOpt;
            $B = (int) $bOpt;
            $C = (int) $cOpt;
            $D = (int) $dOpt;

            if ($A < 0 || $B < 0 || $C < 0 || $D < 0) {
                $this->error('Steps A, B, C, and D must be non-negative integers.');

                return 1;
            }

            $start = $session['player'];
            $destination = $this->tracePath($start, $A, $B, $C, $D);

            if ($destination === null) {
                $this->error("The path from (Row {$start[0]}, Col {$start[1]}) with steps (A={$A}, B={$B}, C={$C}, D={$D}) is blocked by obstacles!");
                $this->displayGrid($session['player'], $session['item']);

                return 1;
            }

            // Update player position
            $session['player'] = $destination;

            // Check if player found the item
            if ($session['player'] === $session['item']) {
                $this->info("🎉 Congratulations! You found the hidden item at (Row {$destination[0]}, Col {$destination[1]})!");
                $this->displayGrid($session['player'], $session['item']);
                Cache::forget('hidden_item_session');

                return 0;
            }

            // Update session cache
            Cache::put('hidden_item_session', $session, now()->addHours(24));

            $this->info("Player moved to: (Row {$destination[0]}, Col {$destination[1]})");
            $this->displayGrid($session['player'], $session['item']);

            return 0;
        }

        // Show current game status
        $this->info('Active Game Session:');
        $this->line("- Player 'X' Position: (Row {$session['player'][0]}, Col {$session['player'][1]})");
        $this->line("- Hidden Item '$' Position: (Row {$session['item'][0]}, Col {$session['item'][1]})");
        $this->line("\nGrid map:");
        $this->displayGrid($session['player'], $session['item']);
        $this->line("\nUse `php artisan play:hidden-item --A=X --B=Y --C=Z --D=W` to move player.");
        $this->line('Use `php artisan play:hidden-item --reset` to restart the game.');

        return 0;
    }

    /**
     * Get or create a game session cache.
     */
    private function getOrCreateSession(bool $reset = false): array
    {
        if ($reset || ! Cache::has('hidden_item_session')) {
            $probableLocations = $this->solveAllPathsFrom($this->start);
            $randomLoc = $probableLocations[array_rand($probableLocations)];

            $session = [
                'player' => $this->start,
                'item' => $randomLoc,
            ];

            Cache::put('hidden_item_session', $session, now()->addHours(24));

            return $session;
        }

        return Cache::get('hidden_item_session');
    }

    /**
     * Trace a specific path sequence and return the final destination coordinate if valid.
     */
    private function tracePath(array $start, int $A, int $B, int $C, int $D): ?array
    {
        $rowsCount = count($this->grid);
        $colsCount = strlen($this->grid[0]);

        $currRow = $start[0];
        $currCol = $start[1];

        // 1. Move Up/North A steps (decrement row)
        for ($i = 1; $i <= $A; $i++) {
            $nextRow = $currRow - 1;
            if ($nextRow < 0 || $this->grid[$nextRow][$currCol] === '#') {
                return null; // Blocked or out of bounds
            }
            $currRow = $nextRow;
        }

        // 2. Move Right/East B steps (increment column)
        for ($i = 1; $i <= $B; $i++) {
            $nextCol = $currCol + 1;
            if ($nextCol >= $colsCount || $this->grid[$currRow][$nextCol] === '#') {
                return null;
            }
            $currCol = $nextCol;
        }

        // 3. Move Down/South C steps (increment row)
        for ($i = 1; $i <= $C; $i++) {
            $nextRow = $currRow + 1;
            if ($nextRow >= $rowsCount || $this->grid[$nextRow][$currCol] === '#') {
                return null;
            }
            $currRow = $nextRow;
        }

        // 4. Move Left/West D steps (decrement column)
        for ($i = 1; $i <= $D; $i++) {
            $nextCol = $currCol - 1;
            if ($nextCol < 0 || $this->grid[$currRow][$nextCol] === '#') {
                return null;
            }
            $currCol = $nextCol;
        }

        return [$currRow, $currCol];
    }

    /**
     * Solve and return all possible destination coordinates reachable by any valid paths.
     */
    private function solveAllPathsFrom(array $start): array
    {
        $probableLocations = [];

        // Grid boundaries are 6x8.
        for ($A = 0; $A <= 5; $A++) {
            for ($B = 0; $B <= 7; $B++) {
                for ($C = 0; $C <= 5; $C++) {
                    for ($D = 0; $D <= 7; $D++) {
                        $destination = $this->tracePath($start, $A, $B, $C, $D);
                        if ($destination !== null) {
                            // Exclude the current position itself from the hidden item locations
                            if ($destination === $start) {
                                continue;
                            }
                            $key = "{$destination[0]},{$destination[1]}";
                            $probableLocations[$key] = $destination;
                        }
                    }
                }
            }
        }

        // Sort keys by row and then column
        ksort($probableLocations);

        return array_values($probableLocations);
    }

    /**
     * Display the grid with player X and target $ dynamically rendered.
     */
    private function displayGrid(array $player, array $item): void
    {
        foreach ($this->grid as $rIdx => $row) {
            $lineOutput = '';
            for ($cIdx = 0; $cIdx < strlen($row); $cIdx++) {
                $char = $row[$cIdx];

                // Replace original X symbol if player moved away from starting position
                if ($char === 'X' && ($rIdx !== $player[0] || $cIdx !== $player[1])) {
                    $char = '.';
                }

                if ($rIdx === $player[0] && $cIdx === $player[1]) {
                    $lineOutput .= 'X';
                } elseif ($rIdx === $item[0] && $cIdx === $item[1]) {
                    $lineOutput .= '$';
                } else {
                    $lineOutput .= $char;
                }
            }
            $this->line($lineOutput);
        }
    }
}
