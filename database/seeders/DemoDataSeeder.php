<?php

namespace Database\Seeders;

use App\Enums\AdvanceKind;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BuyerEntryKind;
use App\Enums\ContractorJobKind;
use App\Enums\ContractorPaymentKind;
use App\Enums\ContractorWork;
use App\Enums\MachineStatus;
use App\Enums\PaperIssueKind;
use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Enums\RepairKind;
use App\Enums\WasteDisposal;
use App\Enums\WasteStage;
use App\Enums\WorkOrderStage;
use App\Models\Attendance;
use App\Models\BookFormat;
use App\Models\BuyerEntry;
use App\Models\CashFloat;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\ContractorPayment;
use App\Models\Designation;
use App\Models\Dispatch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\ForemanSheet;
use App\Models\Godown;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\OutsidePrintReceipt;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Part;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\Purchase;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\WasteEntry;
use App\Models\WorkOrder;
use App\Support\PressMath;
use Illuminate\Database\Seeder;

/**
 * Sample entries shaped on the slips, party ledger and roznamcha photographed on 6 October 2026,
 * so the owner can click through a working system. Local environment only.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', 'owner@etihad.test')->firstOrFail();
        $faisal = User::query()->where('email', 'faisal@etihad.test')->firstOrFail();

        if (PaperReceipt::query()->exists()) {
            // Phase 1 sample data is already here; add the staff sample once.
            if (Employee::query()->doesntExist()) {
                $this->staff($owner, $faisal);
            }
            if (WorkOrder::query()->doesntExist()) {
                $this->production($owner, $faisal);
            }

            return;
        }
        $party = fn (string $name) => Party::query()->where('name', $name)->value('id');
        $paper = fn (string $code) => PaperType::query()->where('code', $code)->value('id');
        $machine = fn (string $code) => Machine::query()->where('code', $code)->value('id');
        $godown = fn (string $name) => Godown::query()->where('name', $name)->value('id');
        $head = fn (string $name) => ExpenseHead::query()->where('name', $name)->value('id');

        $receipts = [
            [5506, 50, 'Imran Iqbal Sahib', 'AA-1975', 776, 'LRL-1819', 'Godown A'],
            [5520, 35, 'Imran Iqbal Sahib', 'AA-1975', 2328, 'LRL-1819', 'Godown A'],
            [5521, 30, 'Imran Iqbal Sahib', 'AA-1975', 3104, 'LRL-1819', 'Godown A'],
            [5522, 27, 'Qadri Paper Mills', 'OF-2030', 1000, 'LES-4402', 'Godown A'],
            [5523, 24, 'Muhammad Sahib', 'PR-2030', 1100, 'LHR-3310', 'Godown A'],
            [5524, 21, 'Imran Iqbal Sahib', 'AA-1975', 4656, 'LRL-1819', 'Godown A'],
            [5525, 18, 'Etihad stock', 'NP-2336', 400, 'LHR-7721', 'Godown B'],
            [5526, 16, 'Etihad stock', 'AR-2335', 320, 'LHR-7721', 'Godown B'],
            [5527, 14, 'Etihad stock', 'BC-2336', 240, 'LHR-7721', 'Godown B'],
            [5528, 3, 'Imran Iqbal Sahib', 'AA-1975', 2328, 'LRL-1819', 'Godown A'],
            [5529, 2, 'Imran Iqbal Sahib', 'AA-1975', 776, 'LRL-1819', 'Godown A'],
            [5530, 1, 'Qadri Paper Mills', 'OF-2030', 500, 'LES-4402', 'Godown A'],
        ];
        foreach ($receipts as [$no, $daysAgo, $p, $code, $qty, $truck, $store]) {
            PaperReceipt::create([
                'slip_no' => (string) $no, 'received_at' => now()->subDays($daysAgo)->setTime(10, 15), 'party_id' => $party($p),
                'paper_type_id' => $paper($code), 'quantity' => $qty, 'truck_no' => $truck, 'godown_id' => $godown($store),
                'received_by' => 'Faisal', 'note' => 'Unloaded in godown', 'created_by' => $owner->id,
            ]);
        }
        PaperReceipt::create([
            'slip_no' => '5531', 'received_at' => now()->setTime(9, 12), 'party_id' => $party('Imran Iqbal Sahib'),
            'paper_type_id' => $paper('AA-1975'), 'quantity' => 1552, 'truck_no' => 'LRL-1819', 'godown_id' => $godown('Godown A'),
            'received_by' => 'Faisal', 'created_by' => $faisal->id,
        ]);

        $issues = [
            [45, PaperIssueKind::InHouse, 'Imran Iqbal Sahib', 'AA-1975', 2400, 'WO-1030', 'Urdu Qaida', 'Machine P-01'],
            [33, PaperIssueKind::InHouse, 'Imran Iqbal Sahib', 'AA-1975', 2600, 'WO-1036', 'Social Studies 4', 'Machine P-01'],
            [26, PaperIssueKind::InHouse, 'Imran Iqbal Sahib', 'AA-1975', 2300, 'WO-1038', 'English Grammar 2A', 'Machine P-03'],
            [19, PaperIssueKind::Outsource, 'Qadri Paper Mills', 'OF-2030', 640, 'WO-1039', 'Mathematics Class 8', 'Al-Noor Press'],
            [12, PaperIssueKind::InHouse, 'Etihad stock', 'NP-2336', 180, 'WO-1040', 'Urdu Qaida', 'Machine P-02'],
            [10, PaperIssueKind::InHouse, 'Etihad stock', 'AR-2335', 290, 'WO-1041', 'Computer Studies 6', 'Machine P-01'],
            [9, PaperIssueKind::InHouse, 'Etihad stock', 'BC-2336', 150, 'WO-1041', 'Computer Studies 6', 'Machine P-01'],
            [5, PaperIssueKind::Return, 'Muhammad Sahib', 'PR-2030', 40, 'RET-014', 'Damaged bundle', 'Muhammad Sahib'],
            [4, PaperIssueKind::InHouse, 'Muhammad Sahib', 'PR-2030', 600, 'WO-1044', 'General Science 7', 'Machine P-03'],
            [2, PaperIssueKind::InHouse, 'Imran Iqbal Sahib', 'AA-1975', 3500, 'WO-1045', 'Islamiyat Class 6', 'Machine P-01'],
            [0, PaperIssueKind::InHouse, 'Imran Iqbal Sahib', 'AA-1975', 494, 'WO-1042', 'Urdu Reader Class 5', 'Machine P-01'],
        ];
        foreach ($issues as [$daysAgo, $kind, $p, $code, $qty, $ref, $book, $to]) {
            PaperIssue::create([
                'issued_on' => today()->subDays($daysAgo), 'kind' => $kind, 'party_id' => $party($p), 'paper_type_id' => $paper($code),
                'quantity' => $qty, 'reference' => $ref, 'book_name' => $book, 'issued_to' => $to, 'wastage_percent' => 3, 'created_by' => $faisal->id,
            ]);
        }

        CashFloat::create(['given_on' => today()->startOfMonth(), 'user_id' => $faisal->id, 'amount' => 100000, 'note' => 'Monthly float', 'created_by' => $owner->id]);

        $expenses = [
            [11, 'Ink', 'Ink traders', 18500, 'Black ink, 25 kg', null],
            [10, 'Glue', 'Glue supplier', 5400, 'Binding glue, 30 kg', null],
            [9, 'Admin', 'Stationer', 700, 'Envelopes, 10 packs', null],
            [9, 'Admin', 'Staff', 580, 'Tea and lunch for night shift', null],
            [8, 'Takiyan', 'Hardware store', 2600, '10 takiyan', null],
            [8, 'Meethi ka tail', 'Oil shop', 1950, 'Machine oil, 3 tins', 'P-01'],
            [7, 'Diesel', 'Diesel depot', 6000, 'Generator, load-shedding', null],
            [6, 'Plates', 'Ali Processor', 1500, 'Plate processing, 12 plates', null],
            [3, 'Petrol', 'Shell, Ravi Road', 4285, 'Delivery van and bike', null],
        ];
        foreach ($expenses as [$daysAgo, $h, $to, $amount, $reason, $m]) {
            Expense::create([
                'spent_on' => today()->subDays(min($daysAgo, now()->day - 1)), 'user_id' => $faisal->id, 'expense_head_id' => $head($h),
                'machine_id' => $m ? $machine($m) : null, 'paid_to' => $to, 'amount' => $amount, 'reason' => $reason,
                'created_by' => $faisal->id, 'status' => ApprovalStatus::Approved,
            ]);
        }
        Expense::create([
            'spent_on' => today(), 'user_id' => $faisal->id, 'expense_head_id' => $head('Machine repair'), 'machine_id' => $machine('P-02'),
            'paid_to' => 'Workshop', 'amount' => 16500, 'reason' => 'Motor rewinding, P-02', 'created_by' => $faisal->id,
            'status' => ApprovalStatus::Pending,
        ]);

        $repairs = [
            [21, 'B-02', 'Drive belt', 'Machinery Market', RepairKind::NewPart, 'Drive belt replaced', 2800, 200, 2600, $owner],
            [2, 'B-01', 'Chain 16B', 'Machinery Market', RepairKind::NewPart, 'Chain 16B, 50 ft', 14800, 0, 14920, $owner],
            [1, 'P-01', 'Bearing GU360', 'Bearing House', RepairKind::NewPart, 'Bearing GU360 × 2', 7000, 0, 7000, $owner],
            [0, 'P-02', 'Roller (rula)', 'Ustad Bashir', RepairKind::Repair, 'Roller (rula) re-rubbered', 10500, 1000, 9500, $faisal],
        ];
        foreach ($repairs as [$daysAgo, $m, $part, $vendor, $kind, $desc, $bill, $disc, $paid, $by]) {
            MachineRepair::create([
                'repaired_on' => today()->subDays($daysAgo), 'machine_id' => $machine($m), 'part_id' => Part::query()->where('name', $part)->value('id'),
                'party_id' => $party($vendor), 'kind' => $kind, 'description' => $desc, 'bill_amount' => $bill, 'discount' => $disc,
                'amount_paid' => $paid, 'invoice_no' => (string) random_int(1000, 9999), 'created_by' => $by->id,
            ]);
        }
        Machine::query()->where('code', 'P-02')->update(['status' => MachineStatus::UnderRepair]);
        Machine::query()->where('code', 'C-01')->update(['status' => MachineStatus::Idle]);

        $item = fn (string $name) => InventoryItem::query()->where('name', $name)->value('id');
        $paperCategory = InventoryCategory::query()->where('name', 'Paper')->value('id');
        $offset = InventoryItem::query()->firstOrCreate(['inventory_category_id' => $paperCategory, 'name' => 'Offset 20 × 30 × 80'], ['unit' => 'rims']);

        foreach ([
            [12, 'Ink Traders', 'Black ink', 25, 740, 'IT-871', $owner],
            [4, 'Ink Traders', 'Black ink', 25, 740, 'IT-882', $owner],
            [9, 'Ink Traders', 'Cyan ink', 10, 960, 'IT-875', $owner],
            [8, 'Glue Supplier', 'Binding glue', 60, 180, 'GS-301', $owner],
            [6, 'Ali Processor', 'CTP plates 20 × 30', 200, 320, 'AP-77', $owner],
        ] as [$daysAgo, $supplier, $name, $qty, $rate, $bill, $by]) {
            Purchase::create([
                'purchased_on' => today()->subDays($daysAgo), 'party_id' => $party($supplier), 'inventory_item_id' => $item($name),
                'quantity' => $qty, 'rate' => $rate, 'amount' => (int) round($qty * $rate), 'bill_no' => $bill, 'created_by' => $by->id,
            ]);
        }
        Purchase::create([
            'purchased_on' => today()->subDay(), 'party_id' => $party('Qadri Paper Mills'), 'inventory_item_id' => $offset->id,
            'quantity' => 500, 'rate' => 5200, 'amount' => 2600000, 'bill_no' => 'QPM-1193', 'created_by' => $faisal->id,
        ]);

        foreach ([
            [10, 'Black ink', 8, 'Machine P-01', 'P-01', 'WO-1041'],
            [5, 'Black ink', 12, 'Machine P-03', 'P-03', 'WO-1044'],
            [3, 'Cyan ink', 4, 'Machine P-01', 'P-01', 'WO-1045'],
            [7, 'Binding glue', 45, 'Rashid Binding House', null, 'WO-1040'],
            [2, 'CTP plates 20 × 30', 170, 'Plate room', null, 'WO-1042'],
        ] as [$daysAgo, $name, $qty, $to, $m, $ref]) {
            InventoryIssue::create([
                'issued_on' => today()->subDays($daysAgo), 'inventory_item_id' => $item($name), 'quantity' => $qty,
                'issued_to' => $to, 'machine_id' => $m ? $machine($m) : null, 'reference' => $ref, 'created_by' => $faisal->id,
            ]);
        }

        $this->staff($owner, $faisal);
        $this->production($owner, $faisal);
    }

    /**
     * Staff from the prototype, with this month's attendance, a mid-month advance, a loan and a foreman sheet.
     */
    private function staff(User $owner, User $faisal): void
    {
        $foreman = Employee::create(['code' => 'E-009', 'name' => 'Ghulam Rasool', 'designation' => 'Foreman', 'monthly_salary' => 55000, 'is_foreman' => true, 'joined_on' => '2019-03-01']);
        $people = [
            ['E-001', 'Muhammad Aslam', 'محمد اسلم', 'Printer, P-01', 45000],
            ['E-002', 'Tariq Mehmood', 'طارق محمود', 'Printer, P-03', 42000],
            ['E-003', 'Zahid Hussain', 'زاہد حسین', 'Plate maker', 38000],
            ['E-004', 'Shahid Ali', 'شاہد علی', 'Binder, B-01', 35000],
            ['E-005', 'Naveed Akhtar', 'نوید اختر', 'Folder, B-02', 32000],
            ['E-006', 'Rizwan Ahmed', 'رضوان احمد', 'Cutter, C-01', 32000],
            ['E-007', 'Kashif Iqbal', 'کاشف اقبال', 'Loader', 28000],
            ['E-008', 'Bilal Khan', 'بلال خان', 'Helper', 27000],
        ];
        foreach (collect($people)->pluck(3)->prepend('Foreman') as $job) {
            Designation::firstOrCreate(['name' => $job]);
        }
        $staff = collect([$foreman]);
        foreach ($people as [$code, $name, $ur, $job, $salary]) {
            $staff->push(Employee::create([
                'code' => $code, 'name' => $name, 'name_ur' => $ur, 'designation' => $job, 'monthly_salary' => $salary,
                'foreman_id' => $foreman->id, 'joined_on' => '2022-01-10',
            ]));
        }

        // Last month and this month so far: present 9 to 9 most days, Sundays off, the odd absence.
        $absences = ['E-002' => [6], 'E-004' => [3, 14], 'E-006' => [9], 'E-008' => [2, 7, 15]];
        foreach ([now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()] as $month) {
            $last = $month->isSameMonth(now()) ? today()->subDay() : $month->copy()->endOfMonth();
            for ($day = $month->copy(); $day->lte($last); $day->addDay()) {
                foreach ($staff as $i => $e) {
                    $status = $day->isSunday() ? AttendanceStatus::Off
                        : (in_array($day->day, $absences[$e->code] ?? [], true) ? AttendanceStatus::Absent : AttendanceStatus::Present);
                    $overtime = $status === AttendanceStatus::Present && ($day->day + $i) % 3 === 0 ? 240 : 0;
                    Attendance::create([
                        'employee_id' => $e->id, 'date' => $day->toDateString(), 'status' => $status,
                        'check_in' => $status === AttendanceStatus::Present ? '09:00' : null,
                        'check_out' => $status === AttendanceStatus::Present ? ($overtime ? '21:00' : '17:00') : null,
                        'overtime_minutes' => $overtime, 'source' => 'device',
                    ]);
                }
            }
        }

        $lastMonth = now()->subMonthNoOverflow();
        foreach ($staff as $e) {
            SalaryAdvance::create([
                'employee_id' => $e->id, 'given_on' => $lastMonth->copy()->day(22)->toDateString(), 'amount' => PressMath::roundToTen($e->monthly_salary / 2),
                'kind' => AdvanceKind::MidMonth, 'created_by' => $owner->id,
            ]);
        }

        $aslam = $staff->firstWhere('code', 'E-001');
        Loan::create(['employee_id' => $aslam->id, 'issued_on' => now()->subMonths(3)->toDateString(), 'amount' => 30000, 'monthly_instalment' => 5000,
            'first_instalment_month' => now()->subMonths(2)->startOfMonth()->toDateString(), 'created_by' => $owner->id]);
        Loan::create(['employee_id' => $staff->firstWhere('code', 'E-004')->id, 'issued_on' => today()->subDays(3)->toDateString(), 'amount' => 20000, 'monthly_instalment' => 2500,
            'first_instalment_month' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(), 'note' => 'Family wedding', 'created_by' => $faisal->id]);

        $sheet = ForemanSheet::create(['given_on' => today()->subDays(2)->toDateString(), 'foreman_id' => $foreman->id, 'amount' => 15000, 'note' => 'Eid advance for the team', 'created_by' => $faisal->id]);
        foreach ([['E-007', 4000], ['E-008', 4000], ['E-005', 3000]] as [$code, $amount]) {
            SalaryAdvance::create(['employee_id' => $staff->firstWhere('code', $code)->id, 'given_on' => $sheet->given_on, 'amount' => $amount,
                'kind' => AdvanceKind::Foreman, 'foreman_sheet_id' => $sheet->id, 'created_by' => $faisal->id, 'status' => $sheet->status]);
        }
        SalaryAdvance::create(['employee_id' => $foreman->id, 'given_on' => $sheet->given_on, 'amount' => 4000, 'kind' => AdvanceKind::Foreman,
            'foreman_sheet_id' => $sheet->id, 'note' => 'Kept by the foreman', 'created_by' => $faisal->id, 'status' => $sheet->status]);
    }

    /**
     * Orders at every stage with contractors, plates, a dispatch, buyers and waste, as in the prototype.
     */
    private function production(User $owner, User $faisal): void
    {
        foreach ([['Al-Noor Press', PartyKind::Press], ['Ilmi Kitab Khana', PartyKind::Buyer], ['Nadeem Book Depot', PartyKind::Buyer], ['Ali Processor', PartyKind::PlateProcessor]] as [$name, $kind]) {
            Party::query()->updateOrCreate(['name' => $name], ['kind' => $kind]);
        }
        $rashid = Contractor::create(['name' => 'Rashid Binding House', 'work' => ContractorWork::Binding, 'form_rate' => 0.6, 'copy_rate' => 2.5]);
        $akram = Contractor::create(['name' => 'Akram Folding', 'work' => ContractorWork::Folding, 'folding_rate' => 35]);

        $party = fn (string $name) => Party::query()->where('name', $name)->value('id');
        $format = fn (string $book) => BookFormat::query()->whereHas('book', fn ($q) => $q->where('name', $book))->firstOrFail();
        $aa = PaperType::query()->where('code', 'AA-1975')->value('id');
        $p01 = Machine::query()->where('code', 'P-01')->value('id');
        $p03 = Machine::query()->where('code', 'P-03')->value('id');

        $order = function (string $book, int $copies, ?int $machine, ?string $press, WorkOrderStage $stage, User $by, int $daysAgo, ?string $buyer = null) use ($format, $aa, $party): WorkOrder {
            $f = $format($book);
            $o = WorkOrder::create([
                'number' => WorkOrder::nextNumber(), 'ordered_on' => today()->subDays($daysAgo)->toDateString(), 'book_format_id' => $f->id,
                'copies' => $copies, 'forms' => $f->forms, 'wastage_percent' => 3,
                'rims_needed' => WorkOrder::rims((float) $f->forms, $copies, 3)['needed'], 'paper_type_id' => $aa, 'party_id' => $party('Imran Iqbal Sahib'),
                'mode' => $press ? PrintMode::Outsource : PrintMode::InHouse, 'machine_id' => $machine, 'press_id' => $press ? $party($press) : null,
                'buyer_id' => $buyer ? $party($buyer) : null, 'due_on' => today()->addDays(14 - $daysAgo)->toDateString(), 'created_by' => $by->id,
            ]);
            if ($o->paper_issue_id && $stage !== WorkOrderStage::Printing) {
                $o->update(['stage' => $stage]);
            }

            return $o->fresh();
        };

        $order('Islamiyat Class 6', 18000, $p01, null, WorkOrderStage::Waiting, $faisal, 1);
        $order('Urdu Reader Class 5', 20000, $p01, null, WorkOrderStage::Printing, $owner, 6, 'Ilmi Kitab Khana');
        $outside = $order('Mathematics Class 8', 15000, null, 'Al-Noor Press', WorkOrderStage::Printing, $owner, 8, 'Nadeem Book Depot');
        $folding = $order('General Science 7', 12000, $p03, null, WorkOrderStage::Folding, $owner, 12, 'Nadeem Book Depot');
        $binding = $order('Urdu Qaida', 40000, $p03, null, WorkOrderStage::Binding, $owner, 18, 'Ilmi Kitab Khana');

        OutsidePrintReceipt::create(['work_order_id' => $outside->id, 'received_on' => today()->subDay()->toDateString(), 'printed_copies' => 7000, 'bill_amount' => 42000, 'created_by' => $faisal->id]);

        foreach ([[$akram, $folding, ContractorJobKind::Folding, 'in_progress'], [$akram, $binding, ContractorJobKind::Folding, 'done'], [$rashid, $binding, ContractorJobKind::Binding, 'in_progress']] as [$c, $o, $kind, $state]) {
            ContractorJob::create([
                'contractor_id' => $c->id, 'work_order_id' => $o->id, 'kind' => $kind, 'job_on' => today()->subDays(3)->toDateString(),
                'forms' => $o->forms, 'copies' => $o->copies, 'folding_rate' => $c->folding_rate, 'form_rate' => $c->form_rate, 'copy_rate' => $c->copy_rate,
                'amount' => ContractorJob::price($kind, (float) $o->forms, $o->copies, (float) $c->folding_rate, (float) $c->form_rate, (float) $c->copy_rate),
                'state' => $state, 'created_by' => $faisal->id,
            ]);
        }
        ContractorPayment::create(['contractor_id' => $rashid->id, 'work_order_id' => $binding->id, 'paid_on' => today()->subDays(2)->toDateString(), 'amount' => 50000, 'kind' => ContractorPaymentKind::Advance, 'created_by' => $owner->id]);
        ContractorPayment::create(['contractor_id' => $akram->id, 'paid_on' => today()->toDateString(), 'amount' => 20000, 'kind' => ContractorPaymentKind::Advance, 'note' => 'Weekly advance', 'created_by' => $faisal->id]);

        PlateJob::create(['party_id' => $party('Ali Processor'), 'work_order_id' => $folding->id, 'job_on' => today()->subDays(11)->toDateString(), 'plates' => 28, 'rate' => 125, 'amount' => 3500, 'paid_on' => today()->subDays(5)->toDateString(), 'created_by' => $faisal->id]);
        PlateJob::create(['party_id' => $party('Ali Processor'), 'work_order_id' => $binding->id, 'job_on' => today()->subDays(4)->toDateString(), 'plates' => 12, 'rate' => 125, 'amount' => 1500, 'created_by' => $faisal->id]);

        $load = Dispatch::create([
            'number' => Dispatch::nextNumber(), 'dispatched_on' => today()->subDay()->toDateString(), 'buyer_id' => $party('Ilmi Kitab Khana'), 'destination' => 'Urdu Bazaar, Lahore',
            'work_order_id' => $binding->id, 'book_format_id' => $binding->book_format_id, 'bundles' => 600, 'copies_per_bundle' => 25, 'loose_copies' => 0, 'copies' => 15000,
            'rims' => 20, 'labour_per_bundle' => 3, 'labour_per_rim' => 10, 'labour_amount' => 2000, 'labour_paid_to' => 'Kashif and team', 'truck_no' => 'LES-5521', 'created_by' => $faisal->id,
        ]);
        BuyerEntry::create(['party_id' => $party('Ilmi Kitab Khana'), 'entry_on' => today()->subDays(20)->toDateString(), 'kind' => BuyerEntryKind::Advance, 'amount' => 150000, 'note' => 'Advance for Urdu Qaida', 'created_by' => $owner->id]);
        BuyerEntry::create(['party_id' => $party('Ilmi Kitab Khana'), 'entry_on' => today()->subDay()->toDateString(), 'kind' => BuyerEntryKind::Adjustment, 'amount' => 60000, 'dispatch_id' => $load->id, 'created_by' => $owner->id]);

        WasteEntry::create(['work_order_id' => $binding->id, 'recorded_on' => today()->subDays(9)->toDateString(), 'stage' => WasteStage::Printing, 'quantity' => 38, 'unit' => 'kg', 'responsible' => 'Machine P-03', 'disposal' => WasteDisposal::Sold, 'sale_amount' => 2280, 'sold_to' => 'Raddi wala', 'created_by' => $faisal->id]);
        WasteEntry::create(['work_order_id' => $binding->id, 'recorded_on' => today()->subDays(2)->toDateString(), 'stage' => WasteStage::Cutting, 'quantity' => 12, 'unit' => 'kg', 'responsible' => 'Rashid Binding House', 'disposal' => WasteDisposal::Kept, 'created_by' => $faisal->id]);
    }
}
