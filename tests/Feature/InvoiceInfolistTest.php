<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceInfolistTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private Company $company;

    private Invoice $invoice;

    private Unit $unit;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = $this->actingInCompany($user);
        $client = Client::create(['name' => 'Acme Industries', 'company_id' => $this->company->id]);

        $this->invoice = Invoice::create([
            'number' => 'INV-4242',
            'title' => 'Consultoria de julho',
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'total' => 100.00,
            'currency' => 'BRL',
            'status' => InvoiceStatusEnum::SENT,
            'due_date' => now()->addDays(5)->toDateString(),
            'extra_info' => 'Informacao publica da fatura',
        ]);

        $this->unit = Unit::create(['name' => 'Hora', 'symbol' => 'h', 'company_id' => $this->company->id]);
        $this->product = Product::create([
            'name' => 'Consultoria',
            'company_id' => $this->company->id,
            'unit_id' => $this->unit->id,
            'price' => 50.00,
            'service' => true,
            'digital' => false,
        ]);

        $this->invoice->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'name' => 'Consultoria senior',
            'quantity' => 2,
            'price' => 50.00,
            'total' => 100.00,
        ]);
    }

    public function test_the_resource_declares_an_infolist(): void
    {
        $page = Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id]);

        $page->assertOk();

        $this->assertSame(
            'infolist',
            $page->instance()->getDefaultTestingSchemaName(),
            'ViewInvoice must render the resource infolist, not the disabled form.',
        );
    }

    public function test_the_infolist_shows_the_invoice_header_and_items(): void
    {
        Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])
            ->assertSee('INV-4242')
            ->assertSee('Consultoria de julho')
            ->assertSee('Acme Industries')
            ->assertSee('Consultoria senior')
            ->assertSee('Informacao publica da fatura');
    }

    /**
     * `Entry::getLabel()` takes `afterLast('.')` of the entry name, so every dotted
     * relation path collapses to its leaf: `client.name`, `project.name` and `unit.name`
     * would all render as the bare word "Name", and `contract.title` would collide with
     * the invoice's own `title` field, both rendering "Title". Each relation entry must
     * carry its own explicit label so the header grid stays unambiguous.
     */
    public function test_the_header_shows_distinct_labels_for_each_relation_entry(): void
    {
        $html = Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])->html();

        $this->assertSame(1, $this->countEntryLabelOccurrences('Client', $html));
        $this->assertSame(1, $this->countEntryLabelOccurrences('Project', $html));
        $this->assertSame(1, $this->countEntryLabelOccurrences('Contract', $html));
        $this->assertSame(1, $this->countEntryLabelOccurrences('Unit', $html));
        $this->assertSame(1, $this->countEntryLabelOccurrences('Title', $html));
        $this->assertSame(
            0,
            $this->countEntryLabelOccurrences('Name', $html),
            'client.name, project.name and unit.name must not collapse to the ambiguous auto-generated "Name" label.',
        );
    }

    private function countEntryLabelOccurrences(string $label, string $html): int
    {
        return preg_match_all(
            '/<div class="fi-in-entry-label" role="term">\s*'.preg_quote($label, '/').'\s*<\/div>/',
            $html,
        );
    }

    public function test_rendering_the_infolist_does_not_query_the_invoice_per_line_item(): void
    {
        // Warm up first: permission/role lookups are statically cached after
        // the first authorization check, so an uncounted render here keeps
        // that one-time cost out of the two measurements below.
        Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])->assertOk();

        $queriesWithOneItem = $this->countTableSelectsRendering($this->invoice, 'invoices');

        $this->addSecondAndThirdItem();

        $queriesWithThreeItems = $this->countTableSelectsRendering($this->invoice, 'invoices');

        $this->assertSame(
            1,
            $queriesWithOneItem,
            "Rendering the infolist for a 1-item invoice issued {$queriesWithOneItem} queries against the invoices table; expected exactly 1 (the initial record fetch).",
        );

        $this->assertSame(
            $queriesWithOneItem,
            $queriesWithThreeItems,
            "Rendering the infolist issued {$queriesWithThreeItems} queries against the invoices table for 3 items vs {$queriesWithOneItem} for 1 item; resolving each line item's currency must not re-query its parent invoice.",
        );
    }

    /**
     * `unit.name` is rendered for every line item, so unless the `unit` relation is
     * eager-loaded alongside `items`, each row fires its own `units` query. The count
     * must stay flat (a single batched query per render) as the item count grows.
     *
     * The pinned value is 2, not 1: `RepeatableEntry::getItems()` — and therefore our
     * `getStateUsing()` closure — is invoked twice per render (once via
     * `getDefaultChildSchemas()`, once via `toEmbeddedHtml()`), a pre-existing Filament
     * quirk unrelated to this fix. What must not happen is that number scaling with the
     * item count.
     */
    public function test_rendering_the_infolist_does_not_query_units_per_line_item(): void
    {
        Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])->assertOk();

        $queriesWithOneItem = $this->countTableSelectsRendering($this->invoice, 'units');

        $this->addSecondAndThirdItem();

        $queriesWithThreeItems = $this->countTableSelectsRendering($this->invoice, 'units');

        $this->assertSame(
            2,
            $queriesWithOneItem,
            "Rendering the infolist for a 1-item invoice issued {$queriesWithOneItem} queries against the units table; expected exactly 2 (one batched eager-load query per getState() call).",
        );

        $this->assertSame(
            $queriesWithOneItem,
            $queriesWithThreeItems,
            "Rendering the infolist issued {$queriesWithThreeItems} queries against the units table for 3 items vs {$queriesWithOneItem} for 1 item; resolving each line item's unit must not re-query per row.",
        );
    }

    private function addSecondAndThirdItem(): void
    {
        $this->invoice->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'name' => 'Consultoria pleno',
            'quantity' => 1,
            'price' => 40.00,
            'total' => 40.00,
        ]);
        $this->invoice->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'name' => 'Consultoria junior',
            'quantity' => 1,
            'price' => 30.00,
            'total' => 30.00,
        ]);
    }

    /**
     * Renders the invoice infolist and counts how many SELECT queries hit the given
     * table, isolating the finding under test (a query-per-item lookup) from unrelated
     * queries, such as one-time permission caching or other relations' own N+1s.
     */
    private function countTableSelectsRendering(Invoice $invoice, string $table): int
    {
        $tableSelects = 0;

        $listener = function (QueryExecuted $query) use (&$tableSelects, $table): void {
            if (str_contains($query->sql, 'from "'.$table.'"')) {
                $tableSelects++;
            }
        };

        DB::listen($listener);
        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])->assertOk();

        return $tableSelects;
    }
}
