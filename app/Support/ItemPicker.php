<?php

namespace App\Support;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Support\Collection;

/**
 * The Item box on Add purchase and Move out: the item's name is typed or picked from the suggestions, like the
 * expense rows. A name used in two categories is shown with its category so each suggestion is one item.
 */
class ItemPicker
{
    /**
     * @param  Collection<int, InventoryItem>  $items  with their category loaded
     * @return array<string, InventoryItem> the text shown (and typed) for each item
     */
    public static function labels(Collection $items): array
    {
        $repeated = $items->countBy(fn (InventoryItem $i) => mb_strtolower(trim($i->name)))->filter(fn (int $n) => $n > 1);

        return $items->mapWithKeys(fn (InventoryItem $i) => [
            ($repeated->has(mb_strtolower(trim($i->name))) ? $i->name.' ('.$i->category?->name.')' : $i->name) => $i,
        ])->all();
    }

    /**
     * The item a typed name stands for, ignoring case and extra spaces; null when there is none.
     *
     * @param  Collection<int, InventoryItem>  $items
     */
    public static function find(string $typed, Collection $items): ?InventoryItem
    {
        $typed = self::clean($typed);
        if ($typed === '') {
            return null;
        }

        foreach (self::labels($items) as $label => $item) {
            if (mb_strtolower(self::clean($label)) === mb_strtolower($typed)) {
                return $item;
            }
        }

        return $items->first(fn (InventoryItem $i) => mb_strtolower(self::clean($i->name)) === mb_strtolower($typed));
    }

    /**
     * A name typed on Add purchase that is not in the list yet becomes a new item under "General", so it can be
     * bought now and picked next time (its category can be changed later from the item's page).
     */
    public static function findOrCreateForPurchase(string $typed): ?InventoryItem
    {
        $items = InventoryItem::query()->with('category')->get();
        $found = self::find($typed, $items);
        if ($found || self::clean($typed) === '') {
            if ($found && ! $found->is_active) {
                $found->update(['is_active' => true]);
            }

            return $found;
        }

        $general = OtherOption::findOrCreate(InventoryCategory::class, 'General', ['tracks_stock' => true, 'is_active' => true]);

        return InventoryItem::query()->create(['inventory_category_id' => $general->getKey(), 'name' => self::clean($typed), 'unit' => 'pcs', 'is_active' => true]);
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
