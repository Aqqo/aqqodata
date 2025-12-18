<?php

namespace Aqqo\OData\Tests\Feature;

use Aqqo\OData\Query;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Account;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Blog;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Category;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Customer;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Document;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Employee;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Event;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Flight;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Invoice;
use Aqqo\OData\Tests\Testclasses\ODataSpec\InvoiceItem;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Message;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Order;
use Aqqo\OData\Tests\Testclasses\ODataSpec\OrderLine;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Post;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Product;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Project;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Review;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Sale;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Segment;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Stock;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Subscription;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Supplier;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Tag;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Tax;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Task;
use Aqqo\OData\Tests\Testclasses\ODataSpec\User;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/**
 * Parse an OData URL query string without treating `;` as a param separator.
 *
 * PHP's parse_str treats `;` as arg separator, but OData `$expand` uses semicolons
 * between nested options.
 *
 * @return array<string, string>
 */
function parseODataQuery(string $query): array
{
    $query = ltrim($query, '?');
    if ($query === '') {
        return [];
    }

    $pairs = explode('&', $query);
    $params = [];

    foreach ($pairs as $pair) {
        if ($pair === '') {
            continue;
        }
        $parts = explode('=', $pair, 2);
        $key = urldecode($parts[0]);
        $value = array_key_exists(1, $parts) ? urldecode($parts[1]) : '';
        $params[$key] = $value;
    }

    return $params;
}

/**
 * Create a Query from an OData URL like "/Orders?$filter=...&$expand=...".
 */
function createQueryFromODataUrl(string $url): Query
{
    $parts = parse_url($url);
    $path = trim((string)($parts['path'] ?? ''), '/');
    $entitySet = strtolower(explode('/', $path)[0] ?? '');
    $params = parseODataQuery((string)($parts['query'] ?? ''));

    $model = match ($entitySet) {
        'orders' => Order::class,
        'customers' => Customer::class,
        'products' => Product::class,
        'categories' => Category::class,
        'sales' => Sale::class,
        'subscriptions' => Subscription::class,
        'employees' => Employee::class,
        'projects' => Project::class,
        'documents' => Document::class,
        'blogs' => Blog::class,
        'users' => User::class,
        'events' => Event::class,
        'invoices' => Invoice::class,
        'flights' => Flight::class,
        'warehouses' => Warehouse::class,
        'messages' => Message::class,
        'accounts' => Account::class,
        default => throw new \InvalidArgumentException("Unknown entity set in URL: {$entitySet}"),
    };

    return Query::for($model, new Request($params));
}

it('supports /Orders?$expand=Customer($expand=Orders($filter=OrderLines/any(l:l/Product/Category/Name eq \'Electronics\')))', function () {
    $electronics = Category::query()->create(['name' => 'Electronics']);
    $furniture = Category::query()->create(['name' => 'Furniture']);

    $p1 = Product::query()->create(['name' => 'Laptop', 'category_int_id' => $electronics->id]);
    $p2 = Product::query()->create(['name' => 'Chair', 'category_int_id' => $furniture->id]);

    $customer = Customer::query()->create(['name' => 'ACME']);

    $orderElectronics = Order::query()->create(['customer_id' => $customer->id]);
    $orderFurniture = Order::query()->create(['customer_id' => $customer->id]);

    OrderLine::query()->create(['order_id' => $orderElectronics->id, 'product_id' => $p1->id, 'price' => 1000]);
    OrderLine::query()->create(['order_id' => $orderFurniture->id, 'product_id' => $p2->id, 'price' => 50]);

    $query = createQueryFromODataUrl(
        "/Orders?\$expand=Customer(\$expand=Orders(\$filter=OrderLines/any(l:l/Product/Category/Name eq 'Electronics')))"
    );

    $rows = $query->get();
    expect($rows)->toBeInstanceOf(Collection::class)->and($rows->count())->toBeGreaterThan(0);

    $first = $rows->first();
    expect($first)->toHaveKey('Customer');
    expect($first['Customer'])->toHaveKey('Orders');
    expect($first['Customer']['Orders'])->toBeInstanceOf(Collection::class);

    // Spec expectation: only orders matching the nested filter are expanded.
    expect($first['Customer']['Orders']->pluck('Id')->all())->toBe([$orderElectronics->id]);
});

it('supports /Sales?$apply=groupby((Region,Year),aggregate(Amount with sum as TotalAmount))&$filter=TotalAmount gt 100000', function () {
    Sale::query()->create(['region' => 'EU', 'year' => 2024, 'amount' => 60000]);
    Sale::query()->create(['region' => 'EU', 'year' => 2024, 'amount' => 60000]);
    Sale::query()->create(['region' => 'US', 'year' => 2024, 'amount' => 10]);

    $query = createQueryFromODataUrl(
        "/Sales?\$apply=groupby((Region,Year),aggregate(Amount with sum as TotalAmount))&\$filter=TotalAmount gt 100000"
    );

    // fwrite(STDERR, $query->toSql() . PHP_EOL);

    // Spec expectation: only EU/2024 remains with TotalAmount 120000.
    $rows = $query->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first())->toMatchArray(['Region' => 'EU', 'Year' => 2024, 'TotalAmount' => 120000]);
});

it('supports /Customers?$filter=Orders/all(o:o/OrderLines/any(l:l/Product/Suppliers/all(s:s/Country ne \'CN\')))', function () {
    $cn = Supplier::query()->create(['country' => 'CN', 'rating' => 5]);
    $nl = Supplier::query()->create(['country' => 'NL', 'rating' => 5]);

    $pOk = Product::query()->create(['name' => 'OK']);
    $pOk->suppliers()->attach([$nl->id]);

    $pBad = Product::query()->create(['name' => 'BAD']);
    $pBad->suppliers()->attach([$cn->id]);

    $c1 = Customer::query()->create(['name' => 'Allowed']);
    $o1 = Order::query()->create(['customer_id' => $c1->id]);
    OrderLine::query()->create(['order_id' => $o1->id, 'product_id' => $pOk->id, 'price' => 1]);

    $c2 = Customer::query()->create(['name' => 'Blocked']);
    $o2 = Order::query()->create(['customer_id' => $c2->id]);
    OrderLine::query()->create(['order_id' => $o2->id, 'product_id' => $pBad->id, 'price' => 1]);

    $query = createQueryFromODataUrl(
        "/Customers?\$filter=Orders/all(o:o/OrderLines/any(l:l/Product/Suppliers/all(s:s/Country ne 'CN')))"
    );

    // dump($query->toSql());

    $rows = $query->get();
    expect($rows->pluck('Name')->all())->toBe(['Allowed']);
});

it('supports /Products?$expand=Reviews($apply=filter(Rating ge 4)/groupby((ReviewerId),aggregate(Rating with avg as AvgRating)))', function () {
    $p = Product::query()->create(['name' => 'P']);
    Review::query()->create(['product_id' => $p->id, 'reviewer_id' => 1, 'rating' => 5]);
    Review::query()->create(['product_id' => $p->id, 'reviewer_id' => 1, 'rating' => 3]);
    Review::query()->create(['product_id' => $p->id, 'reviewer_id' => 2, 'rating' => 4]);

    $query = createQueryFromODataUrl(
        "/Products?\$expand=Reviews(\$apply=filter(Rating ge 4)/groupby((ReviewerId),aggregate(Rating with avg as AvgRating)))"
    );

    // Spec expectation: Reviews expand becomes grouped rows per reviewer with avg of rating>=4.
    $rows = $query->get();
    $first = $rows->first();
    expect($first['Reviews'])->toHaveCount(2);
    expect($first['Reviews'][0])->toHaveKeys(['ReviewerId', 'AvgRating']);
});

it('supports /Subscriptions?$filter=endswith(Status,\'Active\') and RenewalDate add duration\'P30D\' lt now()', function () {
    Subscription::query()->create(['status' => 'TrialActive']);
    Subscription::query()->create(['status' => 'Inactive']);

    $query = createQueryFromODataUrl(
        "/Subscriptions?\$filter=endswith(Status,'Active') and RenewalDate add duration'P30D' lt now()"
    );

    // Spec expectation: date arithmetic + now() are supported.
    expect($query->get())->toBeInstanceOf(Collection::class);
});

it('supports /Employees?$orderby=year(HireDate) desc, length(concat(FirstName,LastName)) asc', function () {
    Employee::query()->create(['hire_date' => '2020-01-01', 'first_name' => 'A', 'last_name' => 'AAAA']);
    Employee::query()->create(['hire_date' => '2022-01-01', 'first_name' => 'B', 'last_name' => 'B']);
    Employee::query()->create(['hire_date' => '2022-01-01', 'first_name' => 'C', 'last_name' => 'CCCCCCCC']);

    $query = createQueryFromODataUrl(
        "/Employees?\$orderby=year(HireDate) desc, length(concat(FirstName,LastName)) asc"
    );

    // Spec expectation: function-based ordering is supported.
    $rows = $query->get();
    expect($rows)->toBeInstanceOf(Collection::class);
});

it('supports /Projects?$filter=Tasks/any(t:t/Assignee/ManagerId eq OwnerId)', function () {
    $mgr = Employee::query()->create(['manager_id' => null]);
    $assigneeOk = Employee::query()->create(['manager_id' => $mgr->id]);
    $assigneeBad = Employee::query()->create(['manager_id' => 999]);

    $p1 = Project::query()->create(['owner_id' => $mgr->id]);
    Task::query()->create(['project_id' => $p1->id, 'assignee_id' => $assigneeOk->id]);

    $p2 = Project::query()->create(['owner_id' => $mgr->id]);
    Task::query()->create(['project_id' => $p2->id, 'assignee_id' => $assigneeBad->id]);

    $query = createQueryFromODataUrl(
        "/Projects?\$filter=Tasks/any(t:t/Assignee/ManagerId eq OwnerId)"
    );

    $rows = $query->get();
    expect($rows->pluck('OwnerId')->all())->toBe([$mgr->id]);
});

it('supports /Documents?$search=\"distributed systems\" AND fault&$filter=Tags/any(t:t eq \'research\')', function () {
    $d1 = Document::query()->create(['body' => 'distributed systems fault tolerance']);
    Tag::query()->create(['document_id' => $d1->id, 't' => 'research']);

    $d2 = Document::query()->create(['body' => 'distributed systems']);
    Tag::query()->create(['document_id' => $d2->id, 't' => 'research']);

    $query = createQueryFromODataUrl(
        "/Documents?\$search=\"distributed systems\" AND fault&\$filter=Tags/any(t:t eq 'research')"
    );

    // Spec expectation: $search supports boolean AND, so only d1 matches.
    $rows = $query->get();
    expect($rows->pluck('Body')->all())->toBe(['distributed systems fault tolerance']);
});

it('supports /Blogs?$expand=Posts($orderby=PublishedDate desc;$top=5;$skip=2)', function () {
    $blog = Blog::query()->create(['name' => 'B']);

    $dates = [
        '2025-01-01 00:00:00',
        '2025-01-02 00:00:00',
        '2025-01-03 00:00:00',
        '2025-01-04 00:00:00',
        '2025-01-05 00:00:00',
        '2025-01-06 00:00:00',
        '2025-01-07 00:00:00',
    ];
    foreach ($dates as $dt) {
        Post::query()->create(['blog_id' => $blog->id, 'published_date' => $dt]);
    }

    $query = createQueryFromODataUrl(
        "/Blogs?\$expand=Posts(\$orderby=PublishedDate desc;\$top=5;\$skip=2)"
    );

    $rows = $query->get();
    $first = $rows->first();
    expect($first['Posts'])->toBeInstanceOf(Collection::class);

    // Spec expectation: order desc then skip 2 then take 5 => dates 2025-01-05..2025-01-01
    expect($first['Posts']->pluck('PublishedDate')->all())->toBe(array_slice(array_reverse($dates), 2, 5));
});

it('supports /Orders?$apply=groupby((CustomerId),aggregate(Amount with sum as Total,Amount with average as Avg))&$filter=Total gt Avg', function () {
    $c = Customer::query()->create(['name' => 'C']);
    Order::query()->create(['customer_id' => $c->id, 'amount' => 10]);
    Order::query()->create(['customer_id' => $c->id, 'amount' => 20]);

    $query = createQueryFromODataUrl(
        "/Orders?\$apply=groupby((CustomerId),aggregate(Amount with sum as Total,Amount with average as Avg))&\$filter=Total gt Avg"
    );

    // Spec expectation: grouped row with Total 30, Avg 15, and passes Total>Avg.
    $rows = $query->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first())->toMatchArray(['CustomerId' => $c->id, 'Total' => 30, 'Avg' => 15]);
});

it('supports /Users?$filter=tolower(trim(concat(FirstName,\' \',LastName))) eq \'john doe\'', function () {
    User::query()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    User::query()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $query = createQueryFromODataUrl(
        "/Users?\$filter=tolower(trim(concat(FirstName,' ',LastName))) eq 'john doe'"
    );

    $rows = $query->get();
    expect($rows)->toHaveCount(1);
});

it('supports /Categories?$expand=Parent($expand=Parent($expand=Parent))', function () {
    $c1 = Category::query()->create(['name' => 'L1']);
    $c2 = Category::query()->create(['name' => 'L2', 'parent_id' => $c1->id]);
    $c3 = Category::query()->create(['name' => 'L3', 'parent_id' => $c2->id]);
    Category::query()->create(['name' => 'L4', 'parent_id' => $c3->id]);

    $query = createQueryFromODataUrl(
        "/Categories?\$expand=Parent(\$expand=Parent(\$expand=Parent))"
    );

    $rows = $query->get();
    $l4 = $rows->firstWhere('Name', 'L4');
    expect($l4)->toHaveKey('Parent');
    expect($l4['Parent'])->toHaveKey('Parent');
    expect($l4['Parent']['Parent'])->toHaveKey('Parent');
});

it('supports /Events?$filter=cast(AttendeesCount,Edm.Int64) gt 0 and OptionalNote ne null', function () {
    Event::query()->create(['attendees_count' => 1, 'optional_note' => 'x']);
    Event::query()->create(['attendees_count' => 0, 'optional_note' => 'x']);

    $query = createQueryFromODataUrl(
        "/Events?\$filter=cast(AttendeesCount,Edm.Int64) gt 0 and OptionalNote ne null"
    );

    // Spec expectation: cast is supported and filters accordingly.
    expect($query->get())->toHaveCount(1);
});

it('supports /Products?$filter=CategoryId in (guid\'11111111-1111-1111-1111-111111111111\',guid\'22222222-2222-2222-2222-222222222222\')', function () {
    Product::query()->create(['name' => 'A', 'category_id' => '11111111-1111-1111-1111-111111111111']);
    Product::query()->create(['name' => 'B', 'category_id' => '33333333-3333-3333-3333-333333333333']);

    $query = createQueryFromODataUrl(
        "/Products?\$filter=CategoryId in (guid'11111111-1111-1111-1111-111111111111',guid'22222222-2222-2222-2222-222222222222')"
    );

    // Spec expectation: guid literals are supported and match "A" only.
    expect($query->get()->pluck('Name')->all())->toBe(['A']);
});

it('supports /Customers?$expand=Orders($filter=OrderLines/any(l:l/Price gt Orders/DiscountLimit))', function () {
    $c = Customer::query()->create(['name' => 'C']);
    $o1 = Order::query()->create(['customer_id' => $c->id, 'discount_limit' => 100]);
    $o2 = Order::query()->create(['customer_id' => $c->id, 'discount_limit' => 10]);

    OrderLine::query()->create(['order_id' => $o1->id, 'price' => 50]);
    OrderLine::query()->create(['order_id' => $o2->id, 'price' => 50]);

    $query = createQueryFromODataUrl(
        "/Customers?\$expand=Orders(\$filter=OrderLines/any(l:l/Price gt Orders/DiscountLimit))"
    );

    $rows = $query->get();
    $first = $rows->first();

    // Spec expectation: the expand filter can reference outer "Orders" scope (DiscountLimit).
    expect($first['Orders']->pluck('Id')->all())->toBe([$o2->id]);
});

it('supports /Invoices?$filter=Items/any(i:i/Taxes/all(t:t/Rate gt 0.2)) and TotalAmount mul 1.21 gt CreditLimit', function () {
    $inv = Invoice::query()->create(['total_amount' => 200, 'credit_limit' => 200]);
    $item = InvoiceItem::query()->create(['invoice_id' => $inv->id]);
    Tax::query()->create(['invoice_item_id' => $item->id, 'rate' => 0.3]);

    $query = createQueryFromODataUrl(
        "/Invoices?\$filter=Items/any(i:i/Taxes/all(t:t/Rate gt 0.2)) and TotalAmount mul 1.21 gt CreditLimit"
    );

    // Spec expectation: arithmetic is supported and this invoice matches (200*1.21 > 200).
    expect($query->get())->toHaveCount(1);
});

it('supports /Flights?$filter=Segments/all(s:s/DepartureTime lt s/ArrivalTime) and Segments/any(s:s/Delay gt duration\'PT30M\')', function () {
    $f = Flight::query()->create(['name' => 'F']);
    Segment::query()->create([
        'flight_id' => $f->id,
        'departure_time' => '2025-01-01 10:00:00',
        'arrival_time' => '2025-01-01 11:00:00',
        'delay_minutes' => 45,
    ]);

    $query = createQueryFromODataUrl(
        "/Flights?\$filter=Segments/all(s:s/DepartureTime lt s/ArrivalTime) and Segments/any(s:s/Delay gt duration'PT30M')"
    );

    // Spec expectation: duration literals are supported and filter matches.
    expect($query->get())->toHaveCount(1);
});

it('supports /Warehouses?$expand=Stock($filter=Quantity gt 0;$expand=Product($expand=Suppliers($filter=Rating ge 4)))', function () {
    $w = Warehouse::query()->create(['name' => 'W']);
    $p = Product::query()->create(['name' => 'P']);
    $s1 = Supplier::query()->create(['country' => 'NL', 'rating' => 5]);
    $s2 = Supplier::query()->create(['country' => 'NL', 'rating' => 3]);
    $p->suppliers()->attach([$s1->id, $s2->id]);

    Stock::query()->create(['warehouse_id' => $w->id, 'product_id' => $p->id, 'quantity' => 1]);
    Stock::query()->create(['warehouse_id' => $w->id, 'product_id' => $p->id, 'quantity' => 0]);

    $query = createQueryFromODataUrl(
        "/Warehouses?\$expand=Stock(\$filter=Quantity gt 0;\$expand=Product(\$expand=Suppliers(\$filter=Rating ge 4)))"
    );

    $rows = $query->get();
    $first = $rows->first();
    expect($first['Stock'])->toBeInstanceOf(Collection::class);
    expect($first['Stock'])->toHaveCount(1);

    $suppliers = $first['Stock']->first()['Product']['Suppliers'];
    expect($suppliers->pluck('Rating')->all())->toBe([5]);
});

it('supports /Messages?$filter=contains(tolower(Body),\'error\') and CreatedAt ge now() sub duration\'P7D\'', function () {
    Message::query()->create(['body' => 'an error happened', 'created_at' => Carbon::now()->subDay()->toDateTimeString()]);

    $query = createQueryFromODataUrl(
        "/Messages?\$filter=contains(tolower(Body),'error') and CreatedAt ge now() sub duration'P7D'"
    );

    // Spec expectation: now()/duration/sub are supported.
    expect($query->get())->toHaveCount(1);
});

it('supports /Accounts?$apply=groupby((Country),aggregate(Balance with sum as TotalBalance))&$orderby=TotalBalance desc', function () {
    Account::query()->create(['country' => 'NL', 'balance' => 10]);
    Account::query()->create(['country' => 'NL', 'balance' => 20]);
    Account::query()->create(['country' => 'US', 'balance' => 5]);

    $query = createQueryFromODataUrl(
        "/Accounts?\$apply=groupby((Country),aggregate(Balance with sum as TotalBalance))&\$orderby=TotalBalance desc"
    );

    // Spec expectation: apply creates TotalBalance and orderby sorts by it.
    $rows = $query->get();
    expect($rows)->toHaveCount(2);
    expect($rows->first())->toMatchArray(['Country' => 'NL', 'TotalBalance' => 30]);
});

