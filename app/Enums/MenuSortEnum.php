<?php

namespace App\Enums;

/**
 * BANDAS DE CENTENA, passo 10 dentro da banda: 100 Trabalho diário, 200
 * Financeiro, 300 Relatórios, 400 Administração, 600 Configurações. A banda 500
 * fica livre como folga.
 *
 * A banda existe porque enum backed em PHP exige valores ÚNICOS — passo 10
 * reiniciando em cada grupo repetiria o 10 cinco vezes e não compilaria. O valor
 * absoluto não tem outro efeito: o Filament agrupa primeiro e ordena dentro do
 * grupo.
 *
 * As dezenas puladas dentro de cada banda são do overlay cloud, que declara as
 * suas em `App\PejotaCloud\Enums\CloudMenuSortEnum`. Os dois conjuntos têm de
 * ser disjuntos — um empate devolve o desempate à ordem de registro, em
 * silêncio.
 */
enum MenuSortEnum: int
{
    case TASKS = 110;
    case WORK_SESSIONS = 120;
    case NOTES = 130;

    case INVOICES = 210;

    case TIMESHEET = 350;
    case EXCHANGE_RATES = 360;

    case CLIENTS = 410;
    case VENDORS = 420;
    case PROJECTS = 430;
    case CONTRACTS = 440;
    case PRODUCTS = 450;
    case SUBSCRIPTIONS = 460;
    case MY_COMPANY = 500;
    case TEAM = 510;

    case STATUSES = 610;
    case UNITS = 620;
    case TAGS = 630;
    case MY_PREFERENCES = 640;
    case COMPANY_MAIL_SETTINGS = 650;
    case COMPANY_SETTINGS = 660;
}
