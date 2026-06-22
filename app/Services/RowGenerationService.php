<?php

namespace App\Services;

class RowGenerationService
{
    /**
     * @return array<int, array{pos_x: float, pos_y: float, rotation: float, label: string, row_label: string, seat_number: string}>
     */
    public function generatePositions(array $config): array
    {
        $count = max(1, min(500, (int) ($config['count'] ?? 10)));
        $spacing = max(4, (float) ($config['spacing'] ?? 36));
        $startX = (float) ($config['start_x'] ?? 0);
        $startY = (float) ($config['start_y'] ?? 0);
        $rotation = (float) ($config['rotation'] ?? 0);
        $curvature = max(0, min(1, (float) ($config['curvature'] ?? 0)));
        $direction = ($config['direction'] ?? 'horizontal') === 'vertical' ? 'vertical' : 'horizontal';
        $rowLabel = (string) ($config['row_label'] ?? 'A');
        $namingScheme = (string) ($config['naming_scheme'] ?? 'row_letter');
        $globalStart = (int) ($config['global_start'] ?? 1);

        $positions = [];
        $rad = deg2rad($rotation);
        $cos = cos($rad);
        $sin = sin($rad);

        for ($i = 0; $i < $count; $i++) {
            $localX = $direction === 'horizontal' ? $i * $spacing : 0;
            $localY = $direction === 'vertical' ? $i * $spacing : 0;

            if ($curvature > 0) {
                $t = $count > 1 ? $i / ($count - 1) : 0;
                $arc = ($t - 0.5) * 2;
                $localY += $arc * $arc * $spacing * 4 * $curvature;
            }

            $rotatedX = $localX * $cos - $localY * $sin;
            $rotatedY = $localX * $sin + $localY * $cos;

            $seatNum = $i + 1;
            $label = $this->buildLabel($namingScheme, $rowLabel, $seatNum, $globalStart + $i);

            $positions[] = [
                'pos_x' => round($startX + $rotatedX, 2),
                'pos_y' => round($startY + $rotatedY, 2),
                'rotation' => $rotation,
                'label' => $label,
                'row_label' => $namingScheme === 'numeric_sequential' ? (string) $seatNum : $rowLabel,
                'seat_number' => (string) $seatNum,
            ];
        }

        return $positions;
    }

    private function buildLabel(string $scheme, string $rowLabel, int $seatNum, int $globalIndex): string
    {
        return match ($scheme) {
            'numeric_sequential' => (string) $globalIndex,
            'numeric_row_prefix' => preg_replace('/\D/', '', $rowLabel).$seatNum,
            default => $rowLabel.$seatNum,
        };
    }
}
