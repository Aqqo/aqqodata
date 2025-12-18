<?php

namespace Aqqo\OData\Tests\Testclasses\ODataAdditional {
    use Aqqo\OData\Attributes\ODataProperty;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\HasMany;

    abstract class BaseModel extends Model
    {
        protected $guarded = [];
        public $timestamps = false;
    }

    class Asset extends BaseModel
    {
        protected $table = 'assets';
        public function locations(): HasMany { return $this->hasMany(Location::class, 'asset_id'); }
    }

    class Location extends BaseModel
    {
        protected $table = 'asset_locations';
        public function asset(): BelongsTo { return $this->belongsTo(Asset::class, 'asset_id'); }
        public function inspections(): HasMany { return $this->hasMany(Inspection::class, 'location_id'); }
    }

    class Inspection extends BaseModel
    {
        protected $table = 'location_inspections';
        public function location(): BelongsTo { return $this->belongsTo(Location::class, 'location_id'); }
    }

    class Customer extends BaseModel
    {
        protected $table = 'customers_additional';

        #[ODataProperty('Country', source: 'country', selectable: true, filterable: true)]
        public string $Country;

        public function orders(): HasMany { return $this->hasMany(Order::class, 'customer_id'); }
    }

    class Order extends BaseModel
    {
        protected $table = 'orders_additional';
        public function orderLines(): HasMany { return $this->hasMany(OrderLine::class, 'order_id'); }
        public function orders(): HasMany { return $this->hasMany(self::class, 'parent_id'); } // self-ref for "Orders/any"
    }

    class OrderLine extends BaseModel
    {
        protected $table = 'order_lines_additional';
        public function order(): BelongsTo { return $this->belongsTo(Order::class, 'order_id'); }
        public function product(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
    }

    class Product extends BaseModel
    {
        protected $table = 'products_additional';
        public function tags(): HasMany { return $this->hasMany(Tag::class, 'product_id'); }
    }

    class Tag extends BaseModel
    {
        protected $table = 'product_tags_additional';
    }

    class Device extends BaseModel
    {
        protected $table = 'devices';
        public function telemetry(): HasMany { return $this->hasMany(Telemetry::class, 'device_id'); }
    }

    class Telemetry extends BaseModel
    {
        protected $table = 'telemetry';
        public function measurements(): HasMany { return $this->hasMany(Measurement::class, 'telemetry_id'); }
    }

    class Measurement extends BaseModel
    {
        protected $table = 'measurements';
    }

    class Employee extends BaseModel
    {
        protected $table = 'employees_additional';
        public function directReports(): HasMany { return $this->hasMany(self::class, 'manager_id'); }
    }

    class Contract extends BaseModel
    {
        protected $table = 'contracts';
    }

    class Ticket extends BaseModel
    {
        protected $table = 'tickets';
        public function comments(): HasMany { return $this->hasMany(Comment::class, 'ticket_id'); }
    }

    class Comment extends BaseModel
    {
        protected $table = 'comments';
        public function author(): BelongsTo { return $this->belongsTo(Author::class, 'author_id'); }
    }

    class Author extends BaseModel
    {
        protected $table = 'authors';
    }

    class Payment extends BaseModel
    {
        protected $table = 'payments';

        #[ODataProperty('Method', source: 'method', selectable: true, filterable: true)]
        public string $Method;

        #[ODataProperty('Amount', source: 'amount', selectable: true, filterable: true)]
        public string $Amount;
    }

    class SystemModel extends BaseModel
    {
        protected $table = 'systems';
        public function components(): HasMany { return $this->hasMany(Component::class, 'system_id'); }
    }

    class Component extends BaseModel
    {
        protected $table = 'components';
        public function metrics(): HasMany { return $this->hasMany(Metric::class, 'component_id'); }
    }

    class Metric extends BaseModel
    {
        protected $table = 'metrics';
    }

    class User extends BaseModel
    {
        protected $table = 'users_additional';
        public function roles(): HasMany { return $this->hasMany(Role::class, 'user_id'); }
        public function permissions(): HasMany { return $this->hasMany(Permission::class, 'user_id'); }
    }

    class Role extends BaseModel
    {
        protected $table = 'roles';
    }

    class Permission extends BaseModel
    {
        protected $table = 'permissions';
    }

    class Vehicle extends BaseModel
    {
        protected $table = 'vehicles';
        public function trips(): HasMany { return $this->hasMany(Trip::class, 'vehicle_id'); }
    }

    class Trip extends BaseModel
    {
        protected $table = 'trips';
    }

    class Course extends BaseModel
    {
        protected $table = 'courses';
        public function enrollments(): HasMany { return $this->hasMany(Enrollment::class, 'course_id'); }
    }

    class Enrollment extends BaseModel
    {
        protected $table = 'enrollments';
        public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    }

    class Student extends BaseModel
    {
        protected $table = 'students';
        public function exams(): HasMany { return $this->hasMany(Exam::class, 'student_id'); }
    }

    class Exam extends BaseModel
    {
        protected $table = 'exams';
    }

    class Sensor extends BaseModel
    {
        protected $table = 'sensors';
        public function readings(): HasMany { return $this->hasMany(Reading::class, 'sensor_id'); }
    }

    class Reading extends BaseModel
    {
        protected $table = 'readings';
    }

    class Organization extends BaseModel
    {
        protected $table = 'organizations';

        #[ODataProperty('Industry', source: 'industry', selectable: true, filterable: true)]
        public string $Industry;

        public function employees(): HasMany { return $this->hasMany(OrgEmployee::class, 'organization_id'); }
    }

    class OrgEmployee extends BaseModel
    {
        protected $table = 'org_employees';
    }

    class FileModel extends BaseModel
    {
        protected $table = 'files';
    }

    class Meeting extends BaseModel
    {
        protected $table = 'meetings';
        public function attendees(): HasMany { return $this->hasMany(Attendee::class, 'meeting_id'); }
    }

    class Attendee extends BaseModel
    {
        protected $table = 'attendees';
    }

    class Invoice extends BaseModel
    {
        protected $table = 'invoices_additional';
        public function lines(): HasMany { return $this->hasMany(InvoiceLine::class, 'invoice_id'); }
    }

    class InvoiceLine extends BaseModel
    {
        protected $table = 'invoice_lines';
    }

    class Shipment extends BaseModel
    {
        protected $table = 'shipments';
        public function events(): HasMany { return $this->hasMany(ShipmentEvent::class, 'shipment_id'); }
    }

    class ShipmentEvent extends BaseModel
    {
        protected $table = 'shipment_events';
    }

    class Application extends BaseModel
    {
        protected $table = 'applications';
    }

    class Server extends BaseModel
    {
        protected $table = 'servers';
        public function disks(): HasMany { return $this->hasMany(Disk::class, 'server_id'); }
    }

    class Disk extends BaseModel
    {
        protected $table = 'disks';
    }

    class Transaction extends BaseModel
    {
        protected $table = 'transactions';
    }

    class Notification extends BaseModel
    {
        protected $table = 'notifications';
        public function recipients(): HasMany { return $this->hasMany(Recipient::class, 'notification_id'); }
    }

    class Recipient extends BaseModel
    {
        protected $table = 'recipients';
    }

    class Plan extends BaseModel
    {
        protected $table = 'plans';
        public function features(): HasMany { return $this->hasMany(Feature::class, 'plan_id'); }
    }

    class Feature extends BaseModel
    {
        protected $table = 'features';
        public function usage(): BelongsTo { return $this->belongsTo(Usage::class, 'usage_id'); }
    }

    class Usage extends BaseModel
    {
        protected $table = 'usages';
    }

    class Report extends BaseModel
    {
        protected $table = 'reports';

        #[ODataProperty('Year', source: 'year', selectable: true, filterable: true)]
        public string $Year;

        #[ODataProperty('Month', source: 'month', selectable: true, filterable: true)]
        public string $Month;

        #[ODataProperty('Views', source: 'views', selectable: true, filterable: true)]
        public string $Views;
    }

    class Queue extends BaseModel
    {
        protected $table = 'queues';
        public function messages(): HasMany { return $this->hasMany(QueueMessage::class, 'queue_id'); }
    }

    class QueueMessage extends BaseModel
    {
        protected $table = 'queue_messages';
    }

    class Building extends BaseModel
    {
        protected $table = 'buildings';
        public function floors(): HasMany { return $this->hasMany(Floor::class, 'building_id'); }
    }

    class Floor extends BaseModel
    {
        protected $table = 'floors';
        public function rooms(): HasMany { return $this->hasMany(Room::class, 'floor_id'); }
    }

    class Room extends BaseModel
    {
        protected $table = 'rooms';
    }

    class ApiKey extends BaseModel
    {
        protected $table = 'api_keys';
    }
}

namespace {
    use Aqqo\OData\Query;
    use Aqqo\OData\Tests\Testclasses\ODataAdditional\{
        ApiKey,
        Application,
        Asset,
        Building,
        Contract,
        Course,
        Customer,
        Device,
        Employee,
        FileModel,
        Invoice,
        Meeting,
        Notification,
        Order,
        Organization,
        Payment,
        Plan,
        Queue,
        Report,
        Sensor,
        Server,
        Shipment,
        SystemModel,
        Ticket,
        Transaction,
        User,
        Vehicle
    };
    use Illuminate\Http\Request;

    function parseODataQueryAdditional(string $query): array
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

    function createQueryFromAdditionalODataUrl(string $url): Query
    {
        $parts = parse_url($url);
        $path = trim((string)($parts['path'] ?? ''), '/');
        $entitySet = strtolower(explode('/', $path)[0] ?? '');
        $params = parseODataQueryAdditional((string)($parts['query'] ?? ''));

        $model = match ($entitySet) {
            'assets' => Asset::class,
            'orders' => Order::class,
            'customers' => Customer::class,
            'devices' => Device::class,
            'employees' => Employee::class,
            'contracts' => Contract::class,
            'tickets' => Ticket::class,
            'payments' => Payment::class,
            'products' => \Aqqo\OData\Tests\Testclasses\ODataAdditional\Product::class,
            'systems' => SystemModel::class,
            'users' => User::class,
            'vehicles' => Vehicle::class,
            'courses' => Course::class,
            'sensors' => Sensor::class,
            'organizations' => Organization::class,
            'files' => FileModel::class,
            'meetings' => Meeting::class,
            'invoices' => Invoice::class,
            'shipments' => Shipment::class,
            'applications' => Application::class,
            'servers' => Server::class,
            'students' => \Aqqo\OData\Tests\Testclasses\ODataAdditional\Student::class,
            'transactions' => Transaction::class,
            'notifications' => Notification::class,
            'plans' => Plan::class,
            'reports' => Report::class,
            'queues' => Queue::class,
            'buildings' => Building::class,
            'apikeys' => ApiKey::class,
            default => throw new InvalidArgumentException("Unknown entity set in URL: {$entitySet}"),
        };

        return Query::for($model, new Request($params));
    }

    $urls = [
        "/Assets?\$filter=Locations/any(l:l/Region eq 'EU' and l/Inspections/all(i:i/Result ne 'Fail'))",
        "/Orders?\$filter=OrderLines/any(l:l/Product/Tags/any(t:t eq 'fragile')) and TotalAmount div 1.21 gt 1000",
        "/Customers?\$apply=groupby((Country),aggregate(Orders/\$count as OrderCount))&\$filter=OrderCount gt 10",
        // has a mismatched ')'
        "/Devices?\$filter=Telemetry/any(t:t/Measurements/all(m:m/Value gt m/Threshold)))",
        "/Employees?\$expand=DirectReports(\$expand=DirectReports(\$filter=Salary gt 100000))",
        "/Contracts?\$filter=StartDate le now() and (EndDate eq null or EndDate ge now())",
        "/Tickets?\$filter=contains(tolower(Title),'timeout') or Comments/any(c:c/Author/Role eq 'Admin')",
        "/Payments?\$apply=groupby((Method),aggregate(Amount with sum as Total))&\$filter=Total gt 50000",
        "/Products?\$filter=Price mul QuantityAvailable gt ReorderLevel add 100",
        "/Systems?\$expand=Components(\$filter=Status eq 'Critical';\$expand=Metrics(\$orderby=Timestamp desc;\$top=1))",
        "/Users?\$filter=Roles/all(r:r ne 'Guest') and Permissions/any(p:p eq 'Write')",
        "/Vehicles?\$filter=Trips/all(t:t/Distance div t/FuelUsed lt 20)",
        "/Courses?\$expand=Enrollments(\$filter=Grade ge 8;\$expand=Student(\$select=Id,Name))",
        "/Sensors?\$filter=Readings/any(r:r/Timestamp ge now() sub duration'PT1H' and r/Value gt r/ExpectedMax)",
        "/Orders?\$filter=not Orders/any(o:o/Status eq 'Cancelled')",
        "/Organizations?\$apply=groupby((Industry),aggregate(Employees/\$count as Headcount))",
        "/Files?\$filter=startswith(Name,'report_') and endswith(Name,'.pdf')",
        "/Meetings?\$filter=Attendees/any(a:a/Optional eq false) and Duration gt duration'PT1H'",
        "/Invoices?\$expand=Lines(\$filter=Discount gt 0;\$orderby=Discount desc)",
        "/Shipments?\$filter=Events/any(e:e/Type eq 'Delayed' and e/Delay gt duration'PT2H')",
        "/Applications?\$filter=cast(Priority,Edm.Int32) ge 3 and Status ne null",
        "/Servers?\$expand=Disks(\$filter=FreeSpace div TotalSpace lt 0.1)",
        "/Students?\$filter=Exams/all(e:e/Score ge e/PassMark)",
        "/Transactions?\$filter=abs(Amount) gt 10000 and Currency ne 'USD'",
        "/Notifications?\$filter=Recipients/all(r:r/Read eq false)",
        "/Plans?\$filter=Features/any(f:f/Limit lt Usage/Current)",
        "/Reports?\$apply=groupby((Year,Month),aggregate(Views with sum as TotalViews))",
        "/Queues?\$filter=Messages/\$count gt MaxSize div 2",
        // mismatched ')'
        "/Buildings?\$expand=Floors(\$expand=Rooms(\$filter=Capacity gt 10)))",
        "/APIKeys?\$filter=ExpiresAt le now() add duration'P14D'",
    ];

    foreach ($urls as $url) {
        $name = $url;

        if (str_contains($url, "/Devices?\$filter=") || str_contains($url, "/Buildings?\$expand=")) {
            it("rejects invalid URL filter syntax: {$name}", function () use ($url) {
                expect(fn () => createQueryFromAdditionalODataUrl($url)->toSql())->toThrow(InvalidArgumentException::class);
            });
            continue;
        }

        it("compiles: {$name}", function () use ($url) {
            $query = createQueryFromAdditionalODataUrl($url);
            expect($query->toSql())->toBeString();
        });
    }
}

