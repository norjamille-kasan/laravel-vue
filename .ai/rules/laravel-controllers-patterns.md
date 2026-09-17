---
paths: [app/Http/Controllers/**]
---

# Laravel Controller Patterns

> **Status:** Complete - extracted from PATTERN_GUIDE.md
> **Last updated:** 2026-09-11
> **Applies to:** app/Http/Controllers/** - Inertia v3 + Wayfinder controllers
> **Companion files:** Frontend (`inertia-vue-patterns.md`), Migrations/Models/Enums (`models-enums-patterns.md`)

---

## 10. Controllers

### 10.1 Resource Controller Shape

One controller per resource, located at `app/Http/Controllers/{Resource}Controller.php`. Resource controllers expose Laravel's seven standard methods: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`. **Custom actions that diverge in fields, validation, or authorization get their own focused controller — never a method bolted onto the resource controller (see 10.11).**

```php
namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('items/Index', [
            'items'      => Item::with('category')->paginate(20),
            'categories' => fn () => Category::all(),
        ]);
    }

    public function show(Item $item): Response
    {
        return Inertia::render('items/Show', ['item' => $item]);
    }

    public function create(): Response
    {
        return Inertia::render('items/Create', [
            'categories' => fn () => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = Item::create($request->validated());

        Inertia::flash('message', 'Item created.');

        return back();
    }

    public function edit(Item $item): Response
    {
        return Inertia::render('items/Edit', ['item' => $item]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->validated());

        return back();
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->delete();

        return back();
    }
}
```

### 10.2 Route Registration

Use `Route::resource()` for the seven standard actions. Resource segment is kebab-case (matches Section 2.4):

```php
// routes/web.php
use App\Http\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

Route::resource('items', ItemController::class);
```

Each divergent action gets its own focused controller (see 10.11):

```php
Route::post('items/{item}/status',  ItemStatusController::class)->name('items.status.update');
Route::post('items/{item}/publish', ItemPublishController::class)->name('items.publish');
Route::post('items/{item}/archive', ItemArchiveController::class)->name('items.archive');
```

### 10.3 Validation — Form Requests

Extract non-trivial validation to `app/Http/Requests/`. One FormRequest per resource action (`Store{Resource}Request`, `Update{Resource}Request`).

```php
namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Item::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }
}
```

**Use FormRequest when:**

- 3+ validation rules
- Conditional logic (`sometimes`, `prepareForValidation`)
- Authorization needs to live with the validation
- The same rules are reused across actions

**Use inline `$request->validate([...])` for:**

- Trivial one-liners (e.g., a single boolean toggle)

### 10.4 Authorization — Policies

Generate Policies alongside Models (`php artisan make:policy ItemPolicy --model=Item`). Call `$this->authorize(...)` at the top of mutating actions:

```php
public function update(UpdateItemRequest $request, Item $item): \Illuminate\Http\RedirectResponse
{
    $this->authorize('update', $item);
    $item->update($request->validated());
    return back();
}
```

If the FormRequest already overrides `authorize()`, Laravel authorizes automatically before the controller runs — no extra `$this->authorize()` call needed in that case.

### 10.5 Data Loading

Apply the patterns from Section 7 (plain value / closure / `defer()` / `optional()`) inside controllers. Always eager-load relationships to avoid N+1:

```php
public function index(): Response
{
    return Inertia::render('items/Index', [
        'items'      => Item::with('category')->latest()->paginate(20), // eager-load
        'categories' => fn () => Category::all(),                       // closure: opt-out on reloads
        'analytics'  => Inertia::defer(fn () => Analytics::summary()),  // below the fold
        'typeahead'  => Inertia::optional(fn () => Item::search(...)),  // on-demand only
    ]);
}
```

### 10.6 Responses

- **GETs:** `return Inertia::render('items/Index', [...]);`
- **Mutations (POST/PUT/DELETE):** `return back();` — preserves modal state, partial reloads, and validation error context.
- **Flash data:** `Inertia::flash('key', 'value'); return back();` or chained: `return Inertia::render(...)->flash('key', 'value');`
- **No JSON responses:** This app is Inertia-only. Do not add API Resources unless explicitly needed.

### 10.7 Folder Organization

All controllers live flat in `app/Http/Controllers/`. Form Requests live flat in `app/Http/Requests/`. Policies live in `app/Policies/`.

```
app/Http/
├── Controllers/
│   ├── Controller.php
│   ├── ItemController.php
│   └── OrderItemController.php
└── Requests/
    ├── StoreItemRequest.php
    └── UpdateItemRequest.php
```

For multi-role apps, see 10.10 (Module Organization).

### 10.8 Architecture

Thin controllers containing only request-handling logic. Three layers collaborate:

| Layer | Role |
|-------|------|
| **Controller** | Request handlers only (Cruddy actions, `store/update/destroy/__invoke`). Validation, authorization, `Inertia::render()`, `Inertia::flash()`, `back()`. Never holds helper methods that aren't request handlers. |
| **`app/Query/{Domain}/*Query`** *(rare)* | Query-builder factories extracted from `Inertia::render()` props only when the inline form crosses the §7.9 threshold (~20 lines OR 3+ chained scopes). Stateless (`__invoke(...): Builder`), container-resolved via method-parameter injection (see §10.12). |
| **`app/Transformer/{Domain}/*Transformer`** | Per-item shape converters used *only* when a helper genuinely **computes or reshapes** (rare — see §10.12 + §10.13). Not for hiding fields. |
| **`app/Services/*`** | Stateful, container-injected collaborators (e.g. `DashboardService`). Existing convention; do not put query-builder assembly or per-item shape work here. |

Default to **passing Eloquent models / collections directly to Inertia** and relying on `$hidden`, casts, and eager loading. See §10.13 "Avoid Excessive Transformation".

### 10.9 Rules

- One controller per resource, flat in `app/Http/Controllers/`.
- Use `Route::resource()` for the seven standard actions.
- Use route model binding via typed params (`Item $item`), not `findOrFail()`.
- Use FormRequest for non-trivial validation; inline for trivial one-liners.
- Use Policies for authorization; either `$this->authorize(...)` in the action or `authorize()` in the FormRequest.
- Always eager-load relationships to avoid N+1.
- `return back()` from mutations; `Inertia::render(...)` from GETs.
- Controllers contain **only request-handling logic**; **inline short queries directly in `Inertia::render()` props** (see §7.9 + Spatie Query Builder). Extract to `app/Query/{Domain}/*Query` only when the inline expression crosses the §7.9 threshold. Per-item reshape → `app/Transformer/` (rare). Don't add a Service / Repository / Action layer unless there's a real second caller.

### 10.10 Module Organization

Multi-role apps split the codebase by user context (Admin, Customer, Vendor, Public, etc.). Each module owns its vertical slice — controllers, requests, policies, layouts, and frontend pages — so role-specific concerns stay isolated.

#### 10.10.1 When to use modules

Use modules when:

- Multiple distinct user roles share an app (admin + customer in one codebase)
- Different roles have different resources, validation rules, or authorization
- Different roles need different layouts or navigation shells

Skip modules if the app has a single user context — flat structure is enough.

#### 10.10.2 Backend layout

```
app/Http/
├── Controllers/
│   ├── Controller.php
│   ├── Admin/
│   │   ├── OrderController.php          // App\Http\Controllers\Admin\OrderController
│   │   └── PublishOrderController.php   // custom action lives in the Admin module
│   └── Customer/
│       └── OrderController.php          // App\Http\Controllers\Customer\OrderController
├── Requests/
│   ├── Admin/
│   │   ├── StoreOrderRequest.php
│   │   └── UpdateOrderRequest.php
│   └── Customer/
│       ├── StoreOrderRequest.php
│       └── UpdateOrderRequest.php
└── ...

app/Policies/
├── Admin/
│   └── OrderPolicy.php
└── Customer/
    └── OrderPolicy.php
```

Namespace convention: `App\Http\Controllers\{Module}\{Resource}Controller`, `App\Http\Requests\{Module}\{Action}{Resource}Request`, `App\Policies\{Module}\{Resource}Policy`.

#### 10.10.3 Routes — one file per module

```
routes/
├── web.php        // public + auth-shared routes
├── admin.php      // admin module — loaded with admin middleware + 'admin' prefix
└── customer.php   // customer module — loaded with auth + 'customer' prefix
```

Example `routes/admin.php`:

```php
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PublishOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::resource('orders', OrderController::class);
        Route::post('orders/{order}/publish', PublishOrderController::class);
    });
```

Example `routes/customer.php`:

```php
use App\Http\Controllers\Customer\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function (): void {
        Route::resource('orders', OrderController::class);
    });
```

Wire the module route files in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    then: function (): void {
        Route::middleware(['web'])
            ->group(__DIR__.'/../routes/admin.php');

        Route::middleware(['web'])
            ->group(__DIR__.'/../routes/customer.php');
    },
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

#### 10.10.4 Policies per module

Two policies can govern the same model differently per role:

```
app/Policies/
├── Admin/OrderPolicy.php    // App\Policies\Admin\OrderPolicy — full CRUD for admins
└── Customer/OrderPolicy.php // App\Policies\Customer\OrderPolicy — view + create only
```

Register both in `AuthServiceProvider::boot()` (or use Laravel 11+ auto-discovery):

```php
Gate::policy(\App\Models\Order::class, \App\Policies\Admin\OrderPolicy::class, 'admin');
Gate::policy(\App\Models\Order::class, \App\Policies\Customer\OrderPolicy::class, 'customer');
```

Then in controllers:

```php
// In Admin\OrderController — authorize against the admin policy
$this->authorize('update', $order);

// In Customer\OrderController — authorize against the customer policy
$this->authorize('update', $order);
```

#### 10.10.5 Frontend mirror

The Vue side mirrors the module structure:

```
resources/js/
├── layouts/
│   ├── AppLayout.vue            // shared / public layout
│   ├── AdminLayout.vue          // admin shell — different sidebar, nav, theme
│   └── CustomerLayout.vue       // customer shell
└── pages/
    ├── Welcome.vue
    ├── admin/
    │   └── order-items/
    │       ├── Index.vue
    │       ├── Show.vue
    │       ├── Create.vue
    │       └── Edit.vue
    └── customer/
        └── order-items/
            ├── Index.vue
            └── Show.vue
```

**Render strings** include the module prefix:

```php
return Inertia::render('admin/order-items/Index', [...]);
return Inertia::render('customer/order-items/Index', [...]);
```

**Layout per page** uses the module's layout:

```vue
<!-- resources/js/pages/admin/order-items/Index.vue -->
<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
</script>
```

**Wayfinder route names** are auto-prefixed by the module's `name()` group:

```ts
// Generated by Wayfinder
admin.orderItems.index().url; // /admin/order-items
customer.orderItems.index().url; // /customer/order-items
```

#### 10.10.6 Module organization rules

- One route file per module (`routes/admin.php`, `routes/customer.php`)
- All module route files registered in `bootstrap/app.php` with the `web` middleware group
- Module route group applies: `prefix('{module}')`, `name('{module}.'), module-specific middleware (e.g., 'admin')`
- Controllers, requests, and policies mirror the module structure on disk and in namespaces
- Vue side mirrors: layouts + page folders prefixed by module
- Render strings always include the module prefix when the page belongs to a module
- Shared resources (e.g., a public landing page) live in the root `pages/` folder with no module prefix

### 10.11 Action Controllers (Cruddy by Design)

Pattern from Adam Wathan's _Cruddy by Design_. The seven standard resource actions stay together because they all manipulate the same resource lifecycle. Any custom action that diverges — different fields, validation, authorization, side effects, or response shape — gets its own focused controller.

#### 10.11.1 The Rule

**Never add a custom action method to a resource controller.** If `ProductController::update()` updates product info, do NOT add `ProductController::updateStatus()` for the inline status toggle. Create `ProductStatusController` with its own `update()` method instead.

#### 10.11.2 Naming

`{Resource}{Action}Controller` — the controller is named after what makes it different, not what HTTP verb it answers to:

| Controller                 | Method       | Why split from `ProductController`           |
| -------------------------- | ------------ | -------------------------------------------- |
| `ProductController`        | 7 standard   | CRUD lifecycle                               |
| `ProductStatusController`  | `update()`   | Different fields, validation, authorization  |
| `ProductPublishController` | `__invoke()` | Single-purpose publish; triggers workflow    |
| `ProductArchiveController` | `store()`    | Records an archive event, separate lifecycle |
| `BulkProductController`    | `store()`    | Bulk operation, not a single-resource update |

The method name (`update`, `store`, `__invoke`) matches the route's verb, not the resource controller's method names.

#### 10.11.3 Routes

The resource still uses `Route::resource()`. Each action controller gets a focused route under the same URL prefix:

```php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStatusController;
use App\Http\Controllers\ProductPublishController;
use Illuminate\Support\Facades\Route;

Route::resource('products', ProductController::class);

Route::post('products/{product}/status',  ProductStatusController::class)
    ->name('products.status.update');
Route::post('products/{product}/publish', ProductPublishController::class)
    ->name('products.publish');
```

#### 10.11.4 Full Example — Status Toggle

Controller (`app/Http/Controllers/ProductStatusController.php`):

```php
namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductStatusRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductStatusController extends Controller
{
    public function update(UpdateProductStatusRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('updateStatus', $product);
        $product->update($request->validated());
        return back();
    }
}
```

FormRequest (`app/Http/Requests/UpdateProductStatusRequest.php`):

```php
namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', Product::class);
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:draft,active,archived']];
    }
}
```

Policy method (`app/Policies/ProductPolicy.php`):

```php
public function updateStatus(User $user, Product $product): bool
{
    return $user->can('manage-products');
}
```

Frontend trigger:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import products from '@/routes/products';

defineProps<{ product: { id: number; status: string } }>();

function toggleStatus(productId: number, status: string) {
    router.patch(products.status.update({ product: productId }).url, {
        status,
    });
}
</script>

<template>
    <Select
        :model-value="product.status"
        @update:model-value="(s) => toggleStatus(product.id, s)"
    >
        <SelectTrigger class="w-40">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="draft">Draft</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="archived">Archived</SelectItem>
        </SelectContent>
    </Select>
</template>
```

#### 10.11.5 When to Split

Split into a focused action controller when ANY of these differs from the standard CRUD action:

- **Fields updated** — toggling `status` is not the same as updating `name/price/description`
- **Validation rules** — different shape, conditional logic, or constraints
- **Authorization** — different permission than the standard update
- **Side effects** — triggers a workflow, fires an event, dispatches a job
- **Response shape** — partial response for an inline UI vs a full page
- **Lifecycle** — archive/restore represent a separate state machine

#### 10.11.6 When NOT to Split

Keep the action on the resource controller when ALL of these match the standard CRUD:

- Same fields, validation, authorization, side effects, response
- The action is just a thin convenience around the existing update logic

#### 10.11.7 Other Cruddy by Design Principles

Apply these throughout:

- **Anemic models** — pure data containers, no domain methods. Don't add `$product->publish()`; create `ProductPublishController` instead.
- **No service / repository / action class layer** — controllers do their own Eloquent queries. Extract only when there's a real second caller (reinforces 10.8).
- **No events by default** — the controller coordinates side effects directly. Use events only when there are 2+ genuinely decoupled listeners.
- **Routes group under the resource URL** — `POST /products/{product}/status`, never `POST /product-statuses`.
- **FormRequest per action** — `UpdateProductStatusRequest` is distinct from `UpdateProductRequest`, even when both update the same model.
- **Policy method per action** — `ProductPolicy::updateStatus()` is distinct from `ProductPolicy::update()`.
- **No helper methods on controllers.** Per-item compute/reshape belongs in `app/Transformer/{Domain}/*Transformer` (rare — see §10.12 + §10.13). **Inline short queries directly in `Inertia::render()` props (see §7.9 + Spatie Query Builder); extract to `app/Query/{Domain}/*Query` only when the inline form crosses the §7.9 threshold.**

### 10.12 Query (rare) & Transformer Isolation

Two lightweight directories exist alongside `app/Services/` for non-handler logic. Both are pure helpers instantiated through Laravel's container method-parameter injection. **`app/Query/` is a rare escape hatch** for queries that don't fit inline; **`app/Transformer/` is reserved for per-item compute/reshape** that isn't reachable from `$model + casts + eager-loaded relations`.

```
app/Query/{Domain}/{Name}Query.php             // rare — only when §7.9 threshold met
app/Transformer/{Domain}/{Name}Transformer.php // rare — only when per-item compute/reshape is required
```

Why: keeps controllers honest about being only request handlers (§10.11), and makes each piece independently testable. **Short queries live inline in `Inertia::render()` props** — see §7.9 for the default form and the threshold that justifies extraction.

#### Query class shape *(use only when §7.9 threshold is met)*

This form applies **only** when the inline `QueryBuilder::for(...)` chain from §7.9 exceeds ~20 lines or chains 3+ scopes/`where`/`has` clauses. In that case lift the call into a class so the controller stays readable and the chain is reusable.

```php
namespace App\Query\Appointment;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class TaggedAppointmentsForUserQuery
{
    /** Runtime inputs arrive as __invoke arguments, not via the constructor. */
    public function __invoke(User $user): Builder
    {
        return Appointment::query()
            ->with(['user', 'services', 'tags.tagger', 'tags.user'])
            ->taggedTo($user)
            ->whereIn('appointments.status', [
                AppointmentStatus::Approved->value,
                AppointmentStatus::Completed->value,
            ])
            ->latest('appointments.updated_at')
            ->makeHidden(['admin_notes', 'test_results', 'test_results_at', 'trf_completed_at']);
    }
}
```

- Suffix: `Query`.
- Class final; **no constructor inputs by default** — runtime data flows through `__invoke(...)` arguments so the container can resolve the class via method-parameter injection.
- One public method: `__invoke(...): Builder` — returns an Eloquent builder, NOT a paginator.
- Domain scopes (`scopeTaggedTo`, etc.) stay on the model; the Query class composes them.
- Per-query field visibility is decided here, via `->makeHidden([...])` / `->only([...])` / `$visible`. See §10.13.

#### Transformer class shape

```php
namespace App\Transformer\Appointment;

use App\Models\Appointment;

final class AppointmentShowTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(Appointment $a): array
    {
        return [
            // computed / derived values only — not a re-shape of every column
            'cover_image_url' => $a->getFirstMedia('cover')?->getUrl(),
            'public_published_at' => $a->public_published_at?->toIso8601String(),
        ];
    }
}
```

- Suffix: `Transformer`.
- Class final; **stateless by default** (no constructor args).
- One public method: `transform(Model $m): array` (or `__invoke(Model $m): array` if you prefer the callable form).
- Use ONLY when a helper genuinely computes/derives a value or projects a public schema — never for hiding fields. If you're just remapping column names, stop and fix the model instead.
- Reserved for the rare cases where the value isn't reachable from `$model + casts + eager-loaded relations`. See §10.13 for what does NOT belong here.

#### Instantiation

For the rare extracted Query / Transformer: default to container method-parameter injection. The controller signature lists the Query / Transformer alongside `Request`; Laravel resolves them automatically.

```php
public function __invoke(
    Request $request,
    AppointmentShowTransformer $showTransformer,
): Response {
    return Inertia::render('core/appointments/Show', [
        'appointment' => fn () => $showTransformer->transform(
            Appointment::query()
                ->with(['user', 'services', 'tags.tagger', 'tags.user'])
                ->where('id', $request->route('appointment'))
                ->firstOrFail()
        ),
    ]);
}
```

(Note: this example shows the **default** — inline query inside the `fn () => ...` closure. Container-inject the Query class only when §7.9's threshold applies.)

Fall back to `new` only when the class's constructor needs a runtime input the container cannot supply:

```php
$query = new TaggedAppointmentsForUserQuery($request->user());
```

#### Decision tree

1. Stateful + needs DI / Cache / config? → `app/Services/`
2. Pure query-builder factory?             → **INLINE in `Inertia::render()` props (§7.9)**; extract to `app/Query/` only when §7.9 threshold is met
3. Pure per-item compute / reshape?         → `app/Transformer/` (rare)
4. Plain field filter / hiding?             → `$hidden` (model) + `->makeHidden([...])` (per query) — see §10.13

### 10.13 Avoid Excessive Transformation

The default for `Inertia::render()` is to hand the Eloquent model or collection directly. Hand-rolled `transform()` / `serializeX()` helpers that re-shape fields are an anti-pattern unless they fall into one of two reasons:

| Reason | Mechanism | Example |
|--------|-----------|---------|
| **Security** — don't leak secrets | Model-level `$hidden`; safe `casts` | `User::$hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']` |
| **Doesn't make sense here** — fields valid on the model but irrelevant to this response | Per-query: `->makeHidden([...])`, `->only([...])`, or `$visible` | Inline render call: `QueryBuilder::for(Item::class)->...->paginate(15)->makeHidden(['admin_notes', ...])` (or, for the rare extracted form, the `__invoke(...)` in `app/Query/...Query.php`) |

Defaults Eloquent + Inertia handle for you, so don't re-do them in code:

| Concern | Where it's handled | Don't |
|--------|--------------------|-------|
| Date formatting (Carbon → ISO 8601) | Eloquent `datetime` cast | Manual `->toIso8601String()` in transformers |
| Enum string values | Eloquent enum cast + JSON serialization | Manual `->value` lookups |
| Relation serialization | `with(...)` then Inertia serialization | Hand-nested arrays |
| Null / missing relations | Inertia renders as `null` | Defensive `?->only([...])` chains |

**Implication for §10.12 Transformer layer.** The `app/Transformer/` directory exists for the rare cases where a helper genuinely **reshepes a value** (computed/derived fields, public-API schema). For ordinary page payloads, drop the manual `transform()` methods entirely — use `$hidden` + `->makeHidden([...])` instead.

**Implication for existing helpers.** Existing controllers may carry `transform()` / `serializeFoo()` methods that pre-date this rule. They are grandfathered but candidates for cleanup when touched — when refactoring an existing page, prefer `$hidden` + `->makeHidden([...])` over preserving the manual mapper.

---

