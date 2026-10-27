<?php

namespace Database\Seeders;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\PartyKind;
use App\Models\Book;
use App\Models\ExpenseHead;
use App\Models\Godown;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\PaperType;
use App\Models\Part;
use App\Models\Party;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Lists from the 6 October 2026 scoping visit: the owner's expense heads, the paper sizes on
 * the receipt slips, the machines and the parties named in the ledgers.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::query()->firstOrCreate(['name' => 'Owner'], [
            'description' => 'Full access. Approves entries and manages users.', 'permissions' => [], 'is_super' => true,
        ]);
        $manager = Role::query()->firstOrCreate(['name' => 'Manager'], [
            'description' => 'Daily entries across modules; cannot approve.', 'permissions' => Role::ManagerPermissions,
        ]);

        User::query()->firstOrCreate(['email' => 'owner@etihad.test'], [
            'name' => 'Sheikh Sahib', 'password' => 'password', 'role_id' => $owner->id, 'locale' => 'en',
        ]);
        User::query()->firstOrCreate(['email' => 'faisal@etihad.test'], [
            'name' => 'Faisal', 'password' => 'password', 'role_id' => $manager->id, 'locale' => 'en',
        ]);

        foreach ([['Godown A', 'گودام اے'], ['Godown B', 'گودام بی']] as [$name, $ur]) {
            Godown::query()->firstOrCreate(['name' => $name], ['name_ur' => $ur]);
        }

        $inventory = [
            ['Paper', 'کاغذ', false, []],
            ['Ink', 'سیاہی', true, [['Black ink', 'kg', 10], ['Cyan ink', 'kg', 5], ['Magenta ink', 'kg', 5], ['Yellow ink', 'kg', 5]]],
            ['Plates', 'پلیٹیں', true, [['CTP plates 20 × 30', 'pcs', 50]]],
            ['Glue', 'گلو', true, [['Binding glue', 'kg', 20]]],
            ['Machine parts', 'مشین کے پرزے', true, [['Bearing GU360', 'pcs', 2]]],
            ['Other', 'دیگر', true, []],
        ];
        foreach ($inventory as [$name, $ur, $tracks, $items]) {
            $category = InventoryCategory::query()->firstOrCreate(['name' => $name], ['name_ur' => $ur, 'tracks_stock' => $tracks]);
            foreach ($items as [$item, $unit, $reorder]) {
                InventoryItem::query()->firstOrCreate(['inventory_category_id' => $category->id, 'name' => $item], ['unit' => $unit, 'reorder_level' => $reorder]);
            }
        }

        $heads = [
            ['Machine repair', 'مشین مرمت', '#262B7E'],
            ['Ink', 'سیاہی', '#3E6FC1'],
            ['Diesel', 'ڈیزل', '#7FA0DE'],
            ['Glue', 'گلو', '#B9CBEE'],
            ['Petrol', 'پیٹرول', '#D69A2D'],
            ['Takiyan', 'ٹکیاں', '#8A8FB8'],
            ['Meethi ka tail', 'میٹھا تیل', '#C7CADB'],
            ['Plates', 'پلیٹیں', '#5A9C7D'],
            ['Binding', 'بائنڈنگ', '#9A6FB0'],
            ['Admin', 'انتظامی', '#B0B5C9'],
            ['Other', 'دیگر', '#E2A79B'],
        ];
        foreach ($heads as [$name, $ur, $color]) {
            ExpenseHead::query()->updateOrCreate(['name' => $name], ['name_ur' => $ur, 'color' => $color]);
        }

        $paper = [
            ['AA-1975', 'AA printing', 68, '19.75 × 29.75', 'rims', 500, 3000],
            ['OF-2030', 'Offset', 80, '20 × 30', 'rims', 500, 400],
            ['PR-2030', 'Printing', 68, '20 × 30', 'rims', 500, 300],
            ['NP-2336', 'Newsprint', 45, '23 × 36', 'rims', 500, 150],
            ['AR-2335', 'Art paper', 128, '23 × 35', 'rims', 500, 80],
            ['BC-2336', 'Bleach card', 250, '23 × 36', 'packets', 100, 60],
        ];
        foreach ($paper as [$code, $name, $gsm, $size, $unit, $sheets, $reorder]) {
            PaperType::query()->updateOrCreate(['code' => $code], [
                'name' => $name, 'gsm' => $gsm, 'size' => $size, 'unit' => $unit, 'sheets_per_unit' => $sheets, 'reorder_level' => $reorder,
            ]);
        }

        $parties = [
            ['Imran Iqbal Sahib', PartyKind::PaperParty],
            ['Qadri Paper Mills', PartyKind::PaperParty],
            ['Muhammad Sahib', PartyKind::PaperParty],
            ['Etihad stock', PartyKind::PaperParty],
            ['Ink Traders', PartyKind::Supplier],
            ['Glue Supplier', PartyKind::Supplier],
            ['Ali Processor', PartyKind::Supplier],
            ['Ustad Bashir', PartyKind::Mechanic],
            ['Bearing House', PartyKind::Mechanic],
            ['Machinery Market', PartyKind::Mechanic],
            ['Al-Noor Press', PartyKind::Press],
        ];
        foreach ($parties as [$name, $kind]) {
            Party::query()->updateOrCreate(['name' => $name], ['kind' => $kind]);
        }

        $machines = [
            ['P-01', 'Heidelberg 4-colour offset', MachineType::Printing],
            ['P-02', 'Single-colour offset', MachineType::Printing],
            ['P-03', 'Two-colour offset', MachineType::Printing],
            ['B-01', 'Perfect binder', MachineType::Binding],
            ['B-02', 'Folding machine', MachineType::Binding],
            ['C-01', 'Paper cutter', MachineType::Binding],
        ];
        foreach ($machines as [$code, $name, $type]) {
            Machine::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'status' => MachineStatus::Running]);
        }

        foreach ([['Roller (rula)', 'رولا'], ['Chain 16B', 'چین'], ['Bearing GU360', 'بیئرنگ'], ['Drive belt', 'بیلٹ'], ['Blanket', 'بلینکٹ']] as [$name, $ur]) {
            Part::query()->updateOrCreate(['name' => $name], ['name_ur' => $ur]);
        }

        $standardPaper = PaperType::query()->where('code', 'AA-1975')->value('id');
        $books = [
            ['Urdu Reader Class 5', 'اردو ریڈر جماعت پنجم', 'Class 5, Urdu', 12],
            ['Islamiyat Class 6', 'اسلامیات جماعت ششم', 'Class 6, Islamiyat', 10],
            ['Mathematics Class 8', 'ریاضی جماعت ہشتم', 'Class 8, Mathematics', 16],
            ['General Science 7', 'جنرل سائنس 7', 'Class 7, Science', 14],
            ['Urdu Qaida', 'اردو قاعدہ', 'Nursery', 4],
        ];
        foreach ($books as [$name, $ur, $class, $forms]) {
            $book = Book::query()->firstOrCreate(['name' => $name], ['name_ur' => $ur, 'class_subject' => $class]);
            if ($book->formats()->doesntExist()) {
                $book->formats()->create([
                    'name' => 'Standard', 'size' => '19.75 × 29.75', 'forms' => $forms, 'pages' => $forms * 16,
                    'paper_type_id' => $standardPaper, 'folding_rate' => 35, 'binding_rate' => 2.5,
                ]);
            }
        }

        if (Setting::get(Setting::ManagerDailyLimit) === null) {
            Setting::put(Setting::ManagerDailyLimit, 15000);
        }
        if (Setting::get(Setting::DefaultWastagePercent) === null) {
            Setting::put(Setting::DefaultWastagePercent, 3);
        }
    }
}
