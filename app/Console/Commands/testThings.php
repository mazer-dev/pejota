<?php

namespace App\Console\Commands;

use App\Models\Tag;
use App\Services\QuotationService;
use Illuminate\Console\Command;

class testThings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-things';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
//        $x = 0;
//        dd(Tag::findFromString('SIG1')->tasks);
//        dd(Tag::findFromString('SIG1')->taggable);
//        Tag::findFromString('SIG1')->each(function (Tag $tag) use ($x) {
//            dump(++$x);
//            dump($tag->taggable);
//            sleep(1);
//        });

        $number = 1;
        $this->info('Formating num ' . $number);
        $this->info((new QuotationService())->formatQuotationNumer($number));
    }
}
