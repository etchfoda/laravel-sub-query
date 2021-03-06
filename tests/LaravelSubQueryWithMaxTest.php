<?php

namespace Alexmg86\LaravelSubQuery\Tests;

use Alexmg86\LaravelSubQuery\Facades\LaravelSubQuery;
use Alexmg86\LaravelSubQuery\ServiceProvider;
use Alexmg86\LaravelSubQuery\Tests\Models\Good;
use Alexmg86\LaravelSubQuery\Tests\Models\Invoice;
use Alexmg86\LaravelSubQuery\Tests\Models\Item;
use Illuminate\Database\Eloquent\Builder;

class LaravelSubQueryWithMaxTest extends DatabaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'laravel-sub-query' => LaravelSubQuery::class,
        ];
    }

    public function testBasic()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
            Good::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $results = Invoice::withMax('items:price,price2');

        $this->assertEquals([
            ['id' => 1, 'name' => 'text_name', 'items_price_max' => 10, 'items_price2_max' => 11],
        ], $results->get()->toArray());
    }

    public function testWithConditions()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
            Good::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $results = Invoice::withMax(['items:price', 'goods:price,price2' => function (Builder $query) {
            $query->where('price', '<', 5);
        }]);

        $this->assertEquals([
            [
                'id' => 1,
                'name' => 'text_name',
                'items_price_max' => 10,
                'goods_price_max' => 4,
                'goods_price2_max' => 5
            ],
        ], $results->get()->toArray());
    }

    public function testWithSelect()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $results = Invoice::select(['id'])->withMax('items:price as price_max');

        $this->assertEquals([
            ['id' => 1, 'price_max' => 10],
        ], $results->get()->toArray());
    }

    public function testLoadMax()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $results = Invoice::first();
        $results->loadMax('items:price');

        $this->assertEquals(['id' => 1, 'name' => 'text_name', 'items_price_max' => 10], $results->toArray());
    }

    public function testLoadMaxWithConditions()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $results = Invoice::first();
        $results->loadMax(['items:price' => function ($query) {
            $query->where('price', '>', 5);
        }]);

        $this->assertEquals(['id' => 1, 'name' => 'text_name', 'items_price_max' => 10], $results->toArray());
    }

    public function testGlobalScopes()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Good::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $result = Invoice::withMax('goods:price')->first();
        $this->assertEquals(4, $result->goods_price_max);

        $result = Invoice::withMax('allGoods:price')->first();
        $this->assertEquals(10, $result->all_goods_price_max);
    }

    public function testSortingScopes()
    {
        $invoice = Invoice::create(['id' => 1, 'name' => 'text_name']);
        for ($i = 1; $i < 11; $i++) {
            Item::create(['invoice_id' => $invoice->id, 'price' => $i, 'price2' => $i + 1]);
        }

        $result = Invoice::withMax('items:price')->toSql();

        $query = 'select "invoices".*, (select max(price) from "items"';
        $query .= ' where "invoices"."id" = "items"."invoice_id") as "items_price_max"';
        $query .= ' from "invoices"';
        $this->assertSame($query, $result);
    }
}
