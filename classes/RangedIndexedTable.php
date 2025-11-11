<?php
declare(strict_types=1);
namespace UOPF;

final class RangedIndexedTable {
    /**
     * The table.
     */
    public readonly array $table;

    /**
     * The maximum index.
     */
    public readonly int $maximum;

    public function __construct(array $table) {
        $maximum = 0;

		foreach ($table as $key => $value) {
			$minimum = $maximum + 1;
			$maximum += $value['rank'];

			$table[$key]['range'] = [
				'minimum' => $minimum,
				'maximum' => $maximum
            ];
		}

        $this->table = $table;
        $this->maximum = $maximum;
    }

    public function get(int $index): int {
        foreach ($this->table as $key => $value) {
            $minimum = $value['range']['minimum'];
            $maximum = $value['range']['maximum'];

            if ($index >= $minimum && $index <= $maximum)
                return $key;
        }

        throw new Exception('Out of range.');
    }
}
