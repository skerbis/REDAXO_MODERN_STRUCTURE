<?php

namespace FriendsOfRedaxo\Mediaplace\Widgets;

use FriendsOfRedaxo\Mediaplace\MetainfoWidget;

/**
 * Dropdown / multi-select widget. Choices come from `options.choices_source`
 * (a raw admin-entered string: either a newline list `value|Label` per line,
 * or a SQL SELECT query -- same convention as REDAXO core's classic Metainfo
 * select/radio/checkbox fields, see metainfo/lib/handler/handler.php).
 * Not translatable -- stored values are language-independent keys, only the
 * choice labels could differ, and those are configured once (not per clang).
 */
class SelectWidget extends MetainfoWidget
{
    public function normalizeValue(mixed $value): mixed
    {
        $options = $this->field->getOptions();
        $multiple = !empty($options['multiple']);
        $validValues = array_map(
            static fn(array $choice): string => $choice['value'],
            self::resolveChoices((string) ($options['choices_source'] ?? '')),
        );

        if ($multiple) {
            $values = is_array($value) ? $value : [];
            $filtered = array_values(array_intersect(array_map('strval', $values), $validValues));
            return [] === $filtered ? null : $filtered;
        }

        $scalar = is_scalar($value) ? trim((string) $value) : '';
        if ('' === $scalar) {
            return null;
        }
        return in_array($scalar, $validValues, true) ? $scalar : null;
    }

    /**
     * Resolves the raw `choices_source` admin input into a list of
     * value/label pairs. Re-run on every render/save (not cached in the DB)
     * so SQL-based choices always reflect current data.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function resolveChoices(string $raw): array
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return [];
        }

        if ('SELECT' === \rex_sql::getQueryType($raw)) {
            try {
                $rows = \rex_sql::factory()->getDBArray($raw, [], \PDO::FETCH_NUM);
            } catch (\rex_sql_exception) {
                return [];
            }

            $choices = [];
            foreach ($rows as $row) {
                $label = (string) ($row[0] ?? '');
                $value = (string) ($row[1] ?? $label);
                if ('' === $value) {
                    continue;
                }
                $choices[] = ['value' => $value, 'label' => $label];
            }
            return $choices;
        }

        $choices = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            if (str_contains($line, '|')) {
                [$val, $lbl] = array_map('trim', explode('|', $line, 2));
            } else {
                $val = $lbl = $line;
            }
            if ('' === $val) {
                continue;
            }
            $choices[] = ['value' => $val, 'label' => $lbl];
        }
        return $choices;
    }
}
