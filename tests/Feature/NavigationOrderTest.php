<?php

namespace Tests\Feature;

use App\Models\User;
use App\PejotaCloud\Providers\PejotaCloudServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

/**
 * A ordem do menu é desenho, não acidente de ordem de registro.
 *
 * `NavigationItem::getSort()` devolve `-1` quando `navigationSort` não é
 * declarado, e o `sortBy` do `NavigationManager` é estável em PHP 8 — então
 * todo item sem sort empata e cai na ordem em que foi registrado. Este teste é
 * o que impede essa ordem de voltar.
 */
class NavigationOrderTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->actingInCompany(User::factory()->create());
    }

    /**
     * Rótulo do grupo → rótulos dos itens, na ordem renderizada.
     *
     * O grupo do Dashboard não tem rótulo (`getLabel()` devolve `null`) e vira
     * a chave `''`, que o Filament ordena antes de todos por `sort = -2`.
     *
     * @return array<string, array<int, string>>
     */
    private function navigationMap(): array
    {
        $map = [];

        foreach (Filament::getNavigation() as $group) {
            /** @var NavigationGroup $group */
            $map[$group->getLabel() ?? ''] = collect($group->getItems())
                ->map(fn (NavigationItem $item): string => $item->getLabel())
                ->values()
                ->all();
        }

        return $map;
    }

    public function test_the_five_groups_render_in_the_designed_order(): void
    {
        $this->assertSame(
            ['', 'Daily work', 'Finance', 'Reports', 'Administration', 'Settings'],
            array_keys($this->navigationMap()),
        );
    }

    /**
     * As asserções de COMPOSIÇÃO deste arquivo só valem numa instalação sem o
     * overlay cloud, que acrescenta 13 itens aos mesmos grupos. O repositório
     * cloud roda esta suíte inteira e NÃO pode editar arquivo do core — fazê-lo
     * cria conflito permanente em todo `git merge opensource/main`. A composição
     * com os dois lados presentes é coberta por
     * `Tests\Feature\PejotaCloud\NavigationOrderTest`.
     *
     * A ordem dos GRUPOS e a resolução dos rótulos valem nos dois repositórios e
     * não passam por aqui.
     */
    private function skipWhenTheCloudOverlayIsInstalled(): void
    {
        if (class_exists(PejotaCloudServiceProvider::class)) {
            $this->markTestSkipped('composicao exata e afirmada no teste do overlay cloud');
        }
    }

    public function test_reports_carries_the_two_read_only_screens_of_the_core(): void
    {
        $this->skipWhenTheCloudOverlayIsInstalled();

        $this->assertSame(
            ['Timesheet', 'Exchange Rates'],
            $this->navigationMap()['Reports'],
        );
    }

    /**
     * `__()` não falha quando a chave falta — devolve a própria chave. Sem esta
     * asserção, o menu em português mostraria "Reports" e a suíte ficaria verde.
     */
    public function test_the_reports_group_label_resolves_in_the_three_locales(): void
    {
        foreach (['en' => 'Reports', 'pt_BR' => 'Relatórios', 'es' => 'Informes'] as $locale => $expected) {
            app()->setLocale($locale);

            $this->assertSame($expected, __('Reports'), "rotulo do grupo Relatorios em {$locale}");
        }

        app()->setLocale('en');
    }
}
