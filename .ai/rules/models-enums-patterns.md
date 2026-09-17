---
paths: [database/migrations/**, app/Models/**, app/Enums/**]
---

# Migrations, Models & Enums Patterns

> **Status:** Complete - extracted from PATTERN_GUIDE.md
> **Last updated:** 2026-09-11
> **Applies to:** database/migrations/**, app/Models/**, app/Enums/** - Eloquent schemas, models, enums
> **Companion files:** Frontend (`inertia-vue-patterns.md`), Controllers (`laravel-controllers-patterns.md`)

---

## 11. Migrations, Models & Enums

### 11.1 Enum Classes

PHP backed enums live in `app/Enums/`. Naming: `{Resource}{Field}` or a domain concept (`OrderStatus.php`, `PaymentMethod.php`).

```php
namespace App\Enums;

enum OrderStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Archived = 'archived';
}
```

- **Type:** string-backed (`enum X: string`). Database stores the string value.
- **Case names:** TitleCase per AGENTS.md.
- **String values:** snake_case or kebab-case (lower-case ASCII).
- **Never** use pure enums or int-backed enums for fields that go to the DB — string-backed makes the DB row human-readable and the cast lossless.

### 11.2 Migration Columns

Always `string('status')`. Never `->enum('status', [...])`.

```php
use App\Enums\OrderStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('orders', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('status')->default(OrderStatus::Draft->value);
    $table->timestamps();
});
```

**Why not `->enum(...)`:**

- Adding a new enum case shouldn't require `ALTER TYPE` on MySQL/PostgreSQL.
- `string()` makes schema diffs identical across database drivers.
- Migration code is the one place `->value` is acceptable (DB defaults only).

### 11.3 Model Casts

Cast the column to the enum class in `casts()`:

```php
namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }
}
```

**Usage — no `->value` anywhere in app code:**

```php
// Comparisons
if ($order->status === OrderStatus::Active) { /* ... */ }

// Creation
$order = Order::create([
    'name'   => 'Sample',
    'status' => OrderStatus::Draft,  // not 'draft'
]);

// Updates
$order->update(['status' => OrderStatus::Archived]);
```

### 11.4 Helper Methods

Add `label()`, `color()`, and a static `options()` to every enum. Use `match($this)` for per-case logic:

```php
namespace App\Enums;

enum OrderStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Draft',
            self::Active   => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft    => 'neutral',
            self::Active   => 'success',
            self::Archived => 'warning',
        };
    }

    /**
     * Dropdown-ready [value => label] pairs for UI components.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
```

- `label()` — human-readable name (UIs, badges)
- `color()` — semantic variant for the shadcn-vue `Badge` component (`default`, `secondary`, `destructive`, `outline`) or any custom token
- `options()` — `[value => label]` map for dropdown components

### 11.5 Form Validation

Use `Rule::enum()` — type-safe, IDE-checked, no magic strings:

```php
namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }
}
```

After validation, `$request->validated('status')` returns the enum instance — no manual conversion in the controller.

### 11.6 Frontend Handling

Enum casts serialize as their **string value** in JSON, not the PHP object. The Vue side sees plain strings.

Share options via `Inertia::share()` or pass per-page:

```php
// In AppServiceProvider::boot() — share globally
use Inertia\Inertia;

public function boot(): void
{
    Inertia::share([
        'orderStatusOptions' => OrderStatus::options(),
        // ...other enums
    ]);
}
```

```php
// Per-page (when the enum is page-specific)
return Inertia::render('orders/Index', [
    'orders'             => $orders,
    'orderStatusOptions' => OrderStatus::options(),
]);
```

Vue side:

```vue
<Select v-model="form.status">
    <SelectTrigger>
        <SelectValue />
    </SelectTrigger>
    <SelectContent>
        <SelectItem
            v-for="option in orderStatusOptions"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </SelectItem>
    </SelectContent>
</Select>
```

For TypeScript enum values, see **12.6** — the convention is `as const` arrays in `resources/js/lib/constants.ts` deriving the union type from runtime values. For paginated list props, see **12.4** — `PaginatedResponse<T>` / `SimplePaginatedResponse<T>` live in `resources/js/types/global.d.ts`. PHP enum remains the source of truth; keep both files in sync when adding cases.

### 11.7 Factory States

Use enum cases in factory definitions and state methods, not raw strings:

```php
namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->words(3, true),
            'status' => OrderStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => OrderStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(['status' => OrderStatus::Archived]);
    }
}
```

Usage in tests/seeders:

```php
Order::factory()->active()->create();
```

### 11.8 Money / Currency Storage

Always store money as **integer cents** in the database. The column name stays natural (`price`, `amount`, `total`) — do **not** suffix it with `_in_cents`. Use an Eloquent `Attribute` cast on the model so app code passes normal figures (`100`, `99.99`) and never thinks in cents.

#### 11.8.1 Migration

```php
Schema::create('products', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->bigInteger('price');         // stores cents
    $table->bigInteger('amount');        // stores cents
    $table->timestamps();
});
```

- Type: `bigInteger` — not `decimal`, not `integer`. Avoids float rounding and supports large amounts.
- Name: `price`, `amount`, `total` — the natural field name. **Never** `price_in_cents`, `amount_cents`, etc.
- Nullable: `bigInteger('price')->nullable()` if the field can be absent.

#### 11.8.2 Model — `Attribute` cast

Use `Illuminate\Database\Eloquent\Casts\Attribute`, **not** inline `* 100` / `/ 100` arithmetic anywhere else in the model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected function price(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value === null ? null : $value / 100,
            set: fn ($value) => $value === null ? null : (int) round($value * 100),
        );
    }
}
```

- **Get** divides by 100, returning the human-readable figure (`99.99`).
- **Set** multiplies by 100 and casts to `int`, returning cents (`9999`).
- **Null-safe**: a null DB value reads as null; setting null stores null. Required for nullable money columns.
- `round()` before the `int` cast avoids float artifacts (`99.99 * 100` evaluates to `9998.999999999998`).
- One `Attribute` method per money column, named after the column.

#### 11.8.3 App code never thinks in cents

```php
$product = Product::create(['name' => 'Mug', 'price' => 99.99]); // stores 9999
$product->price;                                                  // 99.99
$product->update(['price' => 100.00]);                            // stores 10000

if ($product->price >= 50) { /* ... */ }
```

All app code — controllers, jobs, Vue props, forms — passes and reads normal figures. The `Attribute` cast is the **only** place `/100` and `*100` live.

#### 11.8.4 Raw queries and `DB::table()` — manual conversion

The `Attribute` cast only runs through Eloquent. `DB::table()`, query builders, raw SQL, and `DB::select()` return **raw cents**. Convert manually at the call site:

```php
use Illuminate\Support\Facades\DB;

// Read
$row = DB::table('products')->where('id', 1)->first();
$price = $row->price / 100;                          // 99.99

// Write
DB::table('products')->insert([
    'name'  => 'Mug',
    'price' => (int) round(99.99 * 100),             // 9999
]);

// Aggregates — SUM / AVG return cents
$totalCents = DB::table('order_items')->sum('amount');
$total      = $totalCents / 100;
```

Keep the conversion at the call site. Do not "fix" it by changing the column type or adding a global scope.

#### 11.8.5 Factories — normal figures, cast does the work

Factories pass normal figures; the `Attribute` cast multiplies on save:

```php
namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'  => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 10, 1000),  // 10.00 – 1000.00
        ];
    }
}
```

The model reads back as a normal figure, so factory assertions stay clean:

```php
expect($product->price)->toBe(99.99);
```

#### 11.8.6 Validation

Validate in normal figures — same as any other numeric field:

```php
public function rules(): array
{
    return [
        'price' => ['required', 'numeric', 'min:0'],
    ];
}
```

The cast handles storage conversion; the request never sees cents.

### 11.9 Rules

- Migration: always `string()` for enum-backed fields, never `->enum()`
- Enum: string-backed, TitleCase cases, snake_case or kebab-case values
- Location: `app/Enums/`
- Model: cast to enum class in `casts()` — never read `->value` in app code
- Comparisons: `===` against enum cases
- Creation/updates: pass enum cases directly
- Form validation: `Rule::enum(EnumClass::class)`
- UI: `label()`, `color()`, and static `options()` on the enum
- Frontend: share `options()` via `Inertia::share()` or per-page props
- Factories: use enum cases in definition and state methods
- Money: store as integer cents in `bigInteger` columns; column name natural (no `_cents` suffix) (§11.8)
- Money: `Attribute` cast on the model — null-safe, `round()` before `int` cast in the setter (§11.8)
- Money: app code passes/reads normal figures; `/100` and `*100` only live in the cast (§11.8)
- Money: `DB::table()` and raw queries return raw cents — convert manually at the call site (§11.8)
- Money: factories pass normal figures in `definition()`; the cast handles storage conversion (§11.8)

---

