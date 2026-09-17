---
paths: [resources/js/**]
---

# Inertia Vue Patterns

> **Status:** Complete - extracted from PATTERN_GUIDE.md
> **Last updated:** 2026-09-11
> **Applies to:** resources/js/** - Inertia.js v3 + Vue 3 + shadcn-vue pages, layouts, components, types
> **Companion files:** Controllers (`laravel-controllers-patterns.md`), Migrations/Models/Enums (`models-enums-patterns.md`)

---

## 1. Overview

This document establishes guidelines for working with Inertia v3 in this Laravel application. It covers page setup, layouts, forms, navigation, data handling, and integration with Wayfinder.

---

## 2. Page Setup

### 2.1 Persistent Layouts

Inertia v3 uses persistent layouts to keep layout instances alive across navigations. This maintains layout state (sidebar collapse, scroll position) between visits.

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>Page content goes here</div>
</template>
```

### 2.2 Dynamic Layout Props

Use `setLayoutProps()` to pass dynamic data from pages to layouts. This is essential for breadcrumbs and other page-specific layout customization.

```vue
<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import dashboard from '@/routes/dashboard';
import items from '@/routes/items';

defineOptions({ layout: AppLayout });

setLayoutProps({
    breadcrumbs: [
        { label: 'Home', to: dashboard.index().url },
        { label: 'Items', to: items.index().url },
        { label: 'Edit Item' },
    ],
});
</script>
```

**Important:** Call `setLayoutProps()` at the top level of `<script setup>`, not inside lifecycle hooks or functions.

### 2.3 TypeScript Typing for Layout Props

For type-checked layout props, configure the `InertiaConfig` interface in your TypeScript setup:

```ts
// resources/js/app.ts or similar
declare module '@inertiajs/vue3' {
    interface InertiaConfig {
        layoutProps: {
            breadcrumbs?: Array<{ label: string; to?: string }>;
            title?: string;
        };
    }
}
```

Or pass a generic directly:

```ts
setLayoutProps<{ breadcrumbs: Array<{ label: string; to: string }> }>({
    breadcrumbs: [...],
});
```

### 2.4 File Naming Conventions

Files in `resources/js/pages` follow two simple rules:

- **Folders: kebab-case** — `resources/js/pages/order-items/`, or module-prefixed `resources/js/pages/admin/order-items/` for multi-role apps.
- **Page components: PascalCase** — `Index.vue`, `Show.vue`, `Create.vue`, `Edit.vue`

Standard page file names per resource:

| File         | Used for                                            |
| ------------ | --------------------------------------------------- |
| `Index.vue`  | List page (table + filters + pagination)            |
| `Show.vue`   | View-only detail page                               |
| `Create.vue` | Create form (only when opened as a standalone page) |
| `Edit.vue`   | Edit form (only when opened as a standalone page)   |
| `Form.vue`   | Generic form used for both create and edit          |

Example folder structure for an "order items" resource:

```
resources/js/pages/
├── Welcome.vue
└── order-items/
    ├── Index.vue
    ├── Show.vue
    ├── Create.vue
    └── Edit.vue
```

**Laravel-side render strings match the folder name** (kebab-case resource segment + PascalCase page name, no `.vue` extension):

```php
return Inertia::render('order-items/Index', [...]);
return Inertia::render('order-items/Show',  [...]);
return Inertia::render('order-items/Create', [...]);  // standalone Create page only
return Inertia::render('order-items/Edit',  [...]);   // standalone Edit page only

// With modules (multi-role apps): prepend the module segment
return Inertia::render('admin/order-items/Index',   [...]);
return Inertia::render('customer/order-items/Index', [...]);
```

> **Reminder:** Modal pages (`Create.vue`, `Edit.vue`, `Show.vue` opened via `visitModal()`) still follow the file-naming rules above but are not full page visits — they do **not** set a layout or call `setLayoutProps()` (see 6.7).

---

## 3. Routes and Wayfinder

### 3.1 Wayfinder Route Imports

Always import route definitions from Wayfinder-generated files for type-safe URLs:

```vue
<script setup lang="ts">
import dashboard from '@/routes/dashboard';
import items from '@/routes/items';
import categories from '@/routes/categories';
import tags from '@/routes/tags';
</script>
```

### 3.2 Route URL Generation

Invoke the route as a function to get a `RouteDefinition`, then read its `.url` property:

```ts
// Without parameters
items.index().url; // → "/items"

// With parameters
items.edit({ item: 5 }).url; // → "/items/5/edit"
items.comments.index({ item: 5 }).url; // → "/items/5/comments"
```

### 3.3 Programmatic Navigation

```ts
import { router } from '@inertiajs/vue3';
import items from '@/routes/items';

// Simple navigation
router.visit(items.index().url);

// With query parameters
router.get(
    items.index().url,
    { 'filter[is_active]': '1' },
    { preserveState: true },
);

// Other HTTP methods
router.post(store().url, formData);
router.put(update({ item: 5 }).url, formData);
router.delete(destroy({ item: 5 }).url);
```

---

## 4. Forms

### 4.1 useForm Hook

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/ItemController';

const form = useForm({
    name: '',
    price: 0,
    category_id: undefined as number | undefined,
});

function submit() {
    form.post(store().url, {
        onSuccess: () => {
            form.reset();
            isOpen.value = false;
        },
    });
}
</script>
```

### 4.2 Form Reset Timing

In Inertia v3, `form.processing` and `form.progress` remain `true` until the visit is fully complete (in `onFinish` callback), not immediately upon receiving a response. This is different from v2.

### 4.3 Handling Validation Errors

Validation errors are automatically available via `form.errors`. This project uses the shared `FormControl` component (`resources/js/components/FormControl.vue`) as the canonical form primitive. It wraps an input slot, owns the label / hint / description / help / error layout, and resolves the error from either an `:error` prop or the optional `form-errors` provider.

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormControl from '@/components/FormControl.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const form = useForm({ email: '', password: '' });

function submit() {
    form.post(store().url);
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <FormControl
            name="email"
            label="Email"
            type="email"
            required
            :error="form.errors.email"
        >
            <Input id="email" v-model="form.email" :aria-invalid="!!form.errors.email" />
        </FormControl>

        <FormControl
            name="password"
            label="Password"
            required
            :error="form.errors.password"
        >
            <Input id="password" v-model="form.password" type="password" />
        </FormControl>

        <Button type="submit" :disabled="form.processing">Sign in</Button>
    </form>
</template>
```

**Props (FormControl):**

| Prop | Type | Purpose |
| --- | --- | --- |
| `name` | `string` | Field name — used to look up `form-errors` when `:error` is not set |
| `label` | `string` | Visible label (can be overridden with the `#label` slot) |
| `description` | `string?` | Helper text shown above the input |
| `hint` / `help` | `string?` | Right-aligned hint or below-input help text |
| `error` | `string \| boolean` | Direct error message; if unset, resolves from the `form-errors` provider by `name` |
| `required` | `boolean` | Adds a red `*` to the label |
| `size` | `'sm' \| 'md' \| 'lg'` | Reserved for future sizing variants |
| `eagerValidation` | `boolean` | Optional client-side validation hook |
| `validateOnInputDelay` | `number` | Optional debounce for eager validation |

**Conventions:**

- Always wrap inputs in `<FormControl>` — never use raw `<div class="space-y-*">` + `<Label>` for form layout.
- The input inside the default slot must carry `:aria-invalid="!!form.errors.<name>"` so screen readers announce the error state.
- `FormControl` is the **only** form primitive in this project. The shadcn-vue `Field` / `FieldGroup` / `FieldLabel` / `InputError` combo is **not** the documented pattern; do not introduce it.

---

## 5. Navigation Components

### 5.1 Link Component

For navigation that preserves SPA behavior:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import items from '@/routes/items';
</script>

<template>
    <Link :href="items.index().url">View Items</Link>

    <!-- Prefetch on hover (after 75ms) -->
    <Link :href="items.index().url" prefetch>Items</Link>

    <!-- Prefetch on click -->
    <Link :href="items.index().url" prefetch="click">Items</Link>

    <!-- Prefetch on mount -->
    <Link :href="items.index().url" prefetch="mount">Items</Link>
</template>
```

### 5.2 Prefetch Cache Duration

By default, prefetched data is cached for 30 seconds:

```vue
<!-- Custom cache duration -->
<Link :href="items.index().url" prefetch cache-for="1m">Items</Link>
<Link :href="items.index().url" prefetch :cache-for="5000">Items</Link>
```

---

## 6. Modals and Edit Pages

### 6.1 Modal Decision Guide

| Scenario                               | Approach                                              | Why                                    |
| -------------------------------------- | ----------------------------------------------------- | -------------------------------------- |
| Create form (no server data needed)    | `Dialog` with `v-model:open`                          | Inline, no additional request          |
| Create form (needs server data)        | `Dialog` + `HeadlessModal` via `visitModal`           | Fetch dropdown / lookup data on open   |
| Edit form (needs server data)          | `Dialog` + `HeadlessModal` via `visitModal`           | Fetch the record on open               |
| Show / view-only                       | `Dialog` + `HeadlessModal` via `visitModal`           | Fetch the record on open, no submit    |
| Side-panel variant of any of the above | `Sheet` + `HeadlessModal` via `visitModal`            | Same data-fetching reason, slide-in UX |

**Rule:** any modal whose contents need server-side data uses `HeadlessModal` wrapping the shadcn-vue body component (`Dialog` or `Sheet`). The trigger opens the modal URL via `visitModal()` (or `ModalLink` for the declarative form).

### 6.2 Create Modals (No Server Data)

Use a `Dialog` controlled via `v-model:open` for forms that don't need data from the server:

```vue
<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { store } from '@/actions/App/Http/Controllers/ItemController';

const isOpen = ref(false);
const form = useForm({ name: '', price: 0 });

function submit() {
    form.post(store().url, {
        onSuccess: () => {
            form.reset();
            isOpen.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button>
                <Plus class="h-4 w-4" />
                Add Item
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add Item</DialogTitle>
                <DialogDescription>Create a new item.</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit">
                <!-- form fields -->
            </form>
        </DialogContent>
    </Dialog>
</template>
```

### 6.3 The HeadlessModal Pattern (Modal with Server Data)

`HeadlessModal` is the `@inertiaui/modal-vue` host that connects a page component to the modal stack. It does not render any UI of its own — it just exposes the modal's state (`isOpen`, `setOpen`, `close`, `reload`, …) as slot props (or via `defineExpose`) so you can wrap a shadcn-vue body component inside.

#### 6.3.1 Trigger (Index.vue)

```vue
<script setup lang="ts">
import { visitModal } from '@inertiaui/modal-vue';
import { Button } from '@/components/ui/button';
import items from '@/routes/items';

defineProps<{ item: { id: number } }>();

function openEdit(itemId: number) {
    visitModal(items.edit({ item: itemId }).url);
}
</script>

<template>
    <Button @click="openEdit(item.id)">Edit</Button>
</template>
```

To open as a **sheet** (slide-over side panel) instead of a dialog, pass `config: { slideover: true }`:

```ts
visitModal(items.edit({ item: itemId }).url, {
    config: { slideover: true },
});
```

#### 6.3.2 Modal page wrapping a `Dialog` (resources/js/pages/Items/Edit.vue)

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HeadlessModal } from '@inertiaui/modal-vue';
import FormControl from '@/components/FormControl.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { update } from '@/actions/App/Http/Controllers/ItemController';

const props = defineProps<{
    item: { id: number; name: string; price: number };
}>();

const modal = ref<any>(null);
const form = useForm({ name: props.item.name, price: props.item.price });

function submit() {
    form.submit(update({ item: props.item.id }).url, {
        onSuccess: () => modal.value.close(),
    });
}
</script>

<template>
    <HeadlessModal ref="modal" v-slot="{ isOpen, setOpen }">
        <Dialog :open="isOpen" @update:open="setOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Item</DialogTitle>
                    <DialogDescription>Update the item details.</DialogDescription>
                </DialogHeader>

                <form id="edit-item-form" class="space-y-4" @submit.prevent="submit">
                    <FormControl
                        name="name"
                        label="Name"
                        required
                        :error="form.errors.name"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :aria-invalid="!!form.errors.name"
                        />
                    </FormControl>

                    <FormControl
                        name="price"
                        label="Price"
                        required
                        :error="form.errors.price"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model.number="form.price"
                            type="number"
                            :aria-invalid="!!form.errors.price"
                        />
                    </FormControl>
                </form>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="setOpen(false)">
                        Cancel
                    </Button>
                    <Button
                        form="edit-item-form"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </HeadlessModal>
</template>
```

The same template with a **`Sheet`** (slide-over side panel) instead of a `Dialog`:

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HeadlessModal } from '@inertiaui/modal-vue';
import FormControl from '@/components/FormControl.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { update } from '@/actions/App/Http/Controllers/ItemController';

const props = defineProps<{
    item: { id: number; name: string; price: number };
}>();

const modal = ref<any>(null);
const form = useForm({ name: props.item.name, price: props.item.price });

function submit() {
    form.submit(update({ item: props.item.id }).url, {
        onSuccess: () => modal.value.close(),
    });
}
</script>

<template>
    <HeadlessModal ref="modal" v-slot="{ isOpen, setOpen }">
        <Sheet :open="isOpen" @update:open="setOpen">
            <SheetContent side="right" class="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Edit Item</SheetTitle>
                    <SheetDescription>Update the item details.</SheetDescription>
                </SheetHeader>

                <form id="edit-item-form" class="space-y-4 p-4" @submit.prevent="submit">
                    <FormControl
                        name="name"
                        label="Name"
                        required
                        :error="form.errors.name"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :aria-invalid="!!form.errors.name"
                        />
                    </FormControl>

                    <FormControl
                        name="price"
                        label="Price"
                        required
                        :error="form.errors.price"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model.number="form.price"
                            type="number"
                            :aria-invalid="!!form.errors.price"
                        />
                    </FormControl>
                </form>

                <SheetFooter>
                    <Button variant="outline" type="button" @click="setOpen(false)">
                        Cancel
                    </Button>
                    <Button
                        form="edit-item-form"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </HeadlessModal>
</template>
```

`Sheet` accepts the same `open` / `@update:open` API as `Dialog`, and `HeadlessModal` exposes identical slot props for both. Swap `Dialog` ↔ `Sheet` freely — only the outer shadcn-vue component changes. The `side` prop on `SheetContent` accepts `right` (default), `left`, `top`, `bottom`.

#### 6.3.3 Controller (ItemsController.php)

```php
public function edit(Item $item)
{
    return Inertia::render('items/Edit', ['item' => $item]);
}

public function update(Request $request, Item $item)
{
    $data = $request->validate([
        'name'  => ['required', 'string', 'max:255'],
        'price' => ['required', 'numeric', 'min:0'],
    ]);

    $item->update($data);

    return back();
}
```

### 6.4 Show Modal (View-Only)

Same shell as 6.3, no `useForm` and no submit. Use `setOpen(false)` for the Close button.

```vue
<script setup lang="ts">
import { HeadlessModal } from '@inertiaui/modal-vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    item: { id: number; name: string; price: number; created_at: string };
}>();
</script>

<template>
    <HeadlessModal v-slot="{ isOpen, setOpen }">
        <Dialog :open="isOpen" @update:open="setOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Item Details</DialogTitle>
                    <DialogDescription>View item information.</DialogDescription>
                </DialogHeader>

                <dl class="grid gap-2 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-medium">Name</dt>
                        <dd class="col-span-2">{{ item.name }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-medium">Price</dt>
                        <dd class="col-span-2">{{ item.price }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="font-medium">Created</dt>
                        <dd class="col-span-2">{{ item.created_at }}</dd>
                    </div>
                </dl>

                <DialogFooter>
                    <Button @click="setOpen(false)">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </HeadlessModal>
</template>
```

### 6.5 Create Modal With Server Data

Same shell as Edit. The controller's `create()` returns the lookup data the form needs (e.g. category list for a dropdown):

```php
public function create()
{
    return Inertia::render('items/Create', [
        'categories' => Category::orderBy('name')->get(['id', 'name']),
    ]);
}

public function store(Request $request)
{
    $data = $request->validate([
        'name'        => ['required', 'string', 'max:255'],
        'price'       => ['required', 'numeric', 'min:0'],
        'category_id' => ['required', 'exists:categories,id'],
    ]);

    Item::create($data);

    return back();
}
```

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HeadlessModal } from '@inertiaui/modal-vue';
import FormControl from '@/components/FormControl.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/actions/App/Http/Controllers/ItemController';

defineProps<{
    categories: Array<{ id: number; name: string }>;
}>();

const modal = ref<any>(null);
const form = useForm({
    name: '',
    price: 0,
    category_id: undefined as number | undefined,
});

function submit() {
    form.post(store().url, {
        onSuccess: () => {
            form.reset();
            modal.value.close();
        },
    });
}
</script>

<template>
    <HeadlessModal ref="modal" v-slot="{ isOpen, setOpen }">
        <Dialog :open="isOpen" @update:open="setOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Item</DialogTitle>
                    <DialogDescription>Create a new item.</DialogDescription>
                </DialogHeader>

                <form id="create-item-form" class="space-y-4" @submit.prevent="submit">
                    <FormControl
                        name="name"
                        label="Name"
                        required
                        :error="form.errors.name"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :aria-invalid="!!form.errors.name"
                        />
                    </FormControl>

                    <FormControl
                        name="price"
                        label="Price"
                        required
                        :error="form.errors.price"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            v-model.number="form.price"
                            type="number"
                            :aria-invalid="!!form.errors.price"
                        />
                    </FormControl>

                    <FormControl
                        name="category_id"
                        label="Category"
                        :error="form.errors.category_id"
                        v-slot="{ id }"
                    >
                        <Select
                            :model-value="form.category_id?.toString()"
                            @update:model-value="
                                (v) => (form.category_id = v ? Number(v) : undefined)
                            "
                        >
                            <SelectTrigger
                                :id="id"
                                class="w-full"
                                :aria-invalid="!!form.errors.category_id"
                            >
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id.toString()"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormControl>
                </form>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="setOpen(false)">
                        Cancel
                    </Button>
                    <Button
                        form="create-item-form"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </HeadlessModal>
</template>
```

### 6.6 Declarative Trigger — `ModalLink`

For simple cases you can skip the `visitModal()` handler entirely and use `ModalLink` like an Inertia `<Link>`:

```vue
<script setup lang="ts">
import { ModalLink } from '@inertiaui/modal-vue';
import items from '@/routes/items';

defineProps<{ item: { id: number } }>();
</script>

<template>
    <ModalLink
        :href="items.edit({ item: item.id }).url"
        class="text-primary hover:underline"
    >
        Edit
    </ModalLink>
</template>
```

To open as a sheet (side panel) declaratively:

```vue
<ModalLink :href="items.edit({ item: item.id }).url" :slideover="true">
    Edit
</ModalLink>
```

### 6.7 Rules

- **Modal pages (`Edit.vue`, `Show.vue`, `Create.vue`) MUST NOT set a layout or call `setLayoutProps()`.** Modal navigations are not full page visits, so the persistent layout doesn't apply.
- **Modal pages don't need `Inertia::defer()` / `Inertia::optional()`.** The click that opens the modal already defers the fetch — pass data as plain props.
- **Binding the inner `Dialog` / `Sheet`:** use `:open="isOpen"` + `@update:open="setOpen"` from the `HeadlessModal` slot. Do **not** hard-code `:open="true"` — it fights the modal stack.
- **Closing from a Cancel button (inside the slot):** `@click="setOpen(false)"`.
- **Closing from `<script setup>` (e.g. `onSuccess`, after a fetch):** call `modal.value.close()` via the template ref typed `ref<any>(null)`.
- **`<Sheet>` is interchangeable with `<Dialog>`** inside `HeadlessModal` — same slot props, same `open` / `setOpen` API. Pick based on UX: `Sheet` for side-panels, edit drawers, detail peek; `Dialog` for confirmations and forms that need focus.
- **`SheetContent`'s `side` prop** accepts `right` (default), `left`, `top`, `bottom`. Most apps use `right`.
- **Trigger as modal vs sheet:** control at the call site, not in the modal page. Pass `{ config: { slideover: true } }` to `visitModal()` or `:slideover="true"` to `ModalLink`. The page itself does not change.
- **Dialog/Sheet always need a Title.** shadcn-vue requires `DialogTitle` / `SheetTitle` for accessibility — render it visually hidden with `class="sr-only"` if you don't want a visible header.

---

## 7. Data Loading Strategies

### 7.1 Mental Model

Every prop passed from a controller to a page has three timing questions:

1. **Standard visit** — does the initial page load need it?
2. **Subsequent visits / partial reloads** — is it OK to skip it?
3. **User-triggered load** — should it only fetch when the user does something?

Pick the cheapest option that still answers those questions. The four core approaches plus one modifier cover every case.

### 7.2 Decision Guide

| Approach                          | Standard visit                     | Partial reload      | Evaluated         | Use when                                                        |
| --------------------------------- | ---------------------------------- | ------------------- | ----------------- | --------------------------------------------------------------- |
| Plain value (`Model::all()`)      | Always sent                        | Optionally sent     | Always            | Must-have data, cheap to compute                                |
| Closure (`fn () => ...`)          | Always sent                        | Optionally sent     | Only when needed  | Default for useful-but-not-blocking data; saves work on reloads |
| `Inertia::defer(fn () => ...)`    | Skipped, auto-fetched after render | Reloaded each visit | When fetched      | Below-the-fold, slow queries, not needed for first paint        |
| `Inertia::optional(fn () => ...)` | **Never sent**                     | Only when in `only` | Only when fetched | On-demand: search dropdowns, typeahead, modal-only data         |
| `Inertia::always(...)`            | Always sent                        | Always sent         | Always            | Data that must be fresh on every visit (rare)                   |

> Modifiers `Inertia::merge()`, `Inertia::deepMerge()`, and `Inertia::prepend()` chain onto any of the four to control how arrays reconcile across navigations.

### 7.3 Plain Value — Always Eager

Use a plain value when the page cannot render without the data and the query is cheap.

```php
return Inertia::render('items/Index', [
    'items' => Item::with('category')->paginate(20),
]);
```

The prop is sent on every visit and the closure body is evaluated even when a partial reload would have skipped it. If you need to skip it on partial reloads, wrap it in a closure (7.4).

### 7.4 Closure (`fn () => ...`) — Load Initially, Opt-Out on Revisit

The default for data that helps on first render but is not critical. The page renders immediately with the prop; on subsequent visits the closure body only runs if a partial reload explicitly asks for the key.

```php
return Inertia::render('items/Index', [
    'items' => Item::with('category')->paginate(20),  // Plain: always
    'categories' => fn () => Category::all(),         // Closure: skipped if not requested
]);
```

Reach for this whenever you would otherwise write `'x' => Model::all()` — the closure costs nothing on reloads that don't need it.

### 7.5 `Inertia::defer()` — Page First, Data After

Skip the prop on initial render, then auto-fetch it in a follow-up request. Always pair with the `<Deferred>` Vue component so the UI shows a fallback skeleton until the data lands.

```php
return Inertia::render('items/Index', [
    'items' => Item::with('category')->paginate(20),
    'categories' => Inertia::defer(fn () => Category::all()),
]);
```

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DataTable from '@/components/core/DataTable.vue';

defineProps<{ items: Paginated<Item>; categories?: Category[] }>();
</script>

<template>
    <DataTable :rows="items.data" :columns="columns" />

    <Deferred data="categories">
        <template #fallback>
            <Skeleton class="h-9 w-48" />
        </template>

        <Select>
            <SelectTrigger class="w-48">
                <SelectValue placeholder="Filter by category" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id.toString()"
                >
                    {{ category.name }}
                </SelectItem>
            </SelectContent>
        </Select>
    </Deferred>
</template>
```

> **Note:** prefer the project's `DataTable` component (`@/components/core/DataTable.vue`) over hand-rolling `Table` markup for index pages — it already wraps `Table`/`TableHeader`/`TableBody`/`TableRow`/`TableHead`/`TableCell`/`TableEmpty` with sortable columns, row selection, and an empty state.

**Grouping:** pass a group name as the second argument so multiple deferred props share one parallel request:

```php
'teams'    => Inertia::defer(fn () => Team::all(), 'attributes'),
'projects' => Inertia::defer(fn () => Project::all(), 'attributes'),
```

`teams` and `projects` fetch together; ungrouped deferred props fetch in their own request.

**Multiple keys** in one `<Deferred>`:

```vue
<Deferred :data="['teams', 'users']">
    <template #fallback><div>Loading...</div></template>
    <!-- both keys available -->
</Deferred>
```

**Reloading indicator** — keep existing data visible during partial reloads:

```vue
<Deferred data="permissions" #default="{ reloading }">
    <template #fallback><div>Loading...</div></template>
    <div :class="{ 'opacity-50': reloading }"><!-- content --></div>
</Deferred>
```

### 7.6 `Inertia::optional()` — Never Load Unless Asked

Use when the data should not load on initial render **and** should not auto-fetch either. Only resolved when something explicitly asks for it via `router.reload({ only: [...] })`.

Typical case: a search dropdown that should only query when the user opens or types into it.

```php
return Inertia::render('items/Index', [
    'items' => Item::paginate(20),
    'typeahead' => Inertia::optional(fn () => Item::search(request('q'))->limit(20)->get()),
]);
```

On the client, opt in to the prop exactly when needed:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';

const search = ref('');

function onSearchFocus() {
    router.reload({ only: ['typeahead'], data: { q: search.value } });
}

function onSearchInput() {
    router.reload(
        { only: ['typeahead'], data: { q: search.value } },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div class="relative w-full sm:w-64">
        <Search class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
        <Input
            v-model="search"
            type="search"
            placeholder="Search…"
            class="pl-8"
            @focus="onSearchFocus"
            @input="onSearchInput"
        />
    </div>
</template>
```

`optional()` is the right pick when you don't want to pay even a single follow-up request — `defer()` would auto-fetch; `optional()` stays dormant until you ask.

### 7.7 Combining All Three

```php
public function index(Request $request)
{
    return Inertia::render('items/Index', [
        'items'         => Item::with('category')->paginate(20),    // Plain — must-have
        'categories'    => fn () => Category::all(),                 // Closure — useful, skip on reloads that don't ask
        'analytics'     => Inertia::defer(fn () => Analytics::forItems()),  // Below fold, auto-fetch
        'typeahead'     => Inertia::optional(fn () =>                // On-demand, never sent unless asked
            Item::search($request->input('q'))->limit(20)->get()
        ),
    ]);
}
```

### 7.8 Quick Rules

Ask these four questions, in order:

1. **Can the page render at all without it?** → Plain value
2. **Useful at first paint, but heavy / skippable on reloads?** → `fn() =>` closure
3. **Below the fold / not blocking first paint, but still wants it eventually?** → `Inertia::defer()`
4. **Only needed after a user action (search, focus, open modal)?** → `Inertia::optional()`

Default to a closure (`fn () => ...`) whenever you're unsure — it costs nothing extra on standard visits and saves work on partial reloads.

### 7.9 Query Inline in `Inertia::render()` Props — Spatie Query Builder (default for list endpoints)

All list endpoints (`Index.vue` pages, typeahead, any paginated/infinite list) use [`spatie/laravel-query-builder`](https://spatie.be/docs/laravel-query-builder) inline in the `Inertia::render()` call. Inline is the default; only extract to `app/Query/{Domain}/{Name}Query.php` when the inline expression exceeds **~20 lines** or chains **3+ scopes / `where` / `has` clauses** (see §10.12 for the rare extracted form).

```php
// ❌ NOT THIS — hand-rolled paginator assembly
$paginator = Item::query()->where('active', true)->paginate(15);
$items = collect($paginator->items())->map(fn ($i) => [...]);

return Inertia::render('items/Index', [
    'items' => [
        'data' => $items,
        'next_page_url' => $paginator->nextPageUrl(),
        'prev_page_url' => $paginator->previousPageUrl(),
    ],
]);

// ✅ THIS — Spatie Query Builder, inline in the render call
return Inertia::render('items/Index', [
    'items' => fn () => QueryBuilder::for(Item::class)
        ->allowedFilters(['name', 'category_id', 'status'])
        ->allowedSorts(['name', 'price', 'created_at'])
        ->allowedIncludes(['category', 'tags'])
        ->paginate(15)
        ->withQueryString(),
]);
```

**Conventions:**

- Wrap the call in `fn () => ...` so initial paint skips the query and partial reloads can re-run it (see §7.4).
- The `Spatie\QueryBuilder\QueryBuilder` import is required at the top of the controller file.
- Filter / sort / include names come from `?filter[name]=...&sort=-created_at&include=category` request params — no manual `Request::input(...)` plumbing.
- Hand the paginator to Inertia as-is; do not reshape `data` / `next_page_url` / `prev_page_url` (see §7.10).
- **Extract to `app/Query/{Domain}/{Name}Query.php`** only when the inline expression crosses ~20 lines or chains 3+ scopes/`where`/`has` clauses. In that case lift the call into a `__invoke(): Builder` class for readability and reuse — see §10.12.

For each prop, prefer the appropriate loading strategy from §7.2 (plain value, `fn () => …`, `Inertia::defer()`, `Inertia::optional()`).

### 7.10 Pagination — Pass the Paginator; Filter Items Inline

Laravel's `LengthAwarePaginator` already carries everything Inertia needs to serialize the standard shape (`current_page`, `last_page`, `data`, `links`, `meta`, `next_page_url`, `prev_page_url`, …). Hand it to Inertia as-is — **do not** manually reshape the `data / next_page_url / prev_page_url` triple.

The existing project's `<Pagination>` Vue component reads `prev_page_url` / `next_page_url`, both present on the live paginator, so no TypeScript or template changes are needed.

#### Hiding fields on a paginated result

Two patterns are acceptable, in order of preference:

1. **Model-level `$hidden`** — when a field is **never** exposed to any client, set it once on the model:

   ```php
   // app/Models/Appointment.php
   protected $hidden = ['admin_notes'];
   ```

2. **Per-paginator `makeHidden`** — when a field is valid on the model but irrelevant to one specific view. `Builder::makeHidden()` doesn't exist, so apply it on each model via `->through()` after `->paginate()`:

   ```php
   'appointments' => fn () => Appointment::query()
       ->with(['user', 'services', 'tags'])
       ->taggedTo($request->user())
       ->whereIn('status', ['approved', 'completed'])
       ->latest('updated_at')
       ->paginate(15)
       ->withQueryString()
       ->through(fn (Appointment $a) => $a->makeHidden([
           'test_results',
           'test_results_at',
           'trf_completed_at',
       ])),
   ```

`Collection::makeHidden([...])` is also available on the paginator's `getCollection()` if you'd rather avoid `->through()`:

```php
->paginate(15)->getCollection()->makeHidden([...])
```

Both forms mutate the hydrated model's `$hidden` array for the request lifetime — that's fine because `toArray()`/`toJson()` runs only once per Inertia payload.

Use `->through(callable)` only when the callback genuinely computes or reshapes a value (e.g., a derived label, a public-API projection) — see §10.12 for the rare cases that justify a Transformer.

---

## 8. Flash Data

### 8.1 Flashing Data from Controllers

```php
public function store(Request $request)
{
    $item = Item::create($request->validated());

    Inertia::flash('message', 'Item created successfully!');

    return back();
}

// Or multiple values
    Inertia::flash([
        'message' => 'Item created!',
        'newItemId' => $item->id,
    ]);

return back();
```

### 8.2 Chaining Flash with Render

```php
return Inertia::render('items/Index', [
    'items' => $items,
])->flash('highlight', $item->id);
```

### 8.3 Accessing Flash Data in Vue

Flash data is automatically available in `page.props.flash`:

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// Access flash data
console.log(page.props.flash.message);
</script>
```

---

## 9. Shared Data

### 9.1 Sharing Data Globally

In `AppServiceProvider::boot()`:

```php
use Inertia\Inertia;

public function boot(): void
{
    Inertia::share([
        'user' => auth()->user(),
        'appName' => config('app.name'),
    ]);
}
```

### 9.2 Accessing Shared Data

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
console.log(page.props.user);
console.log(page.props.appName);
</script>
```

---

## 12. Frontend Types

### 12.1 Model Type Files

Every PHP model gets a TypeScript counterpart at `resources/js/types/models/{Model}.ts` (PascalCase filename matching the Eloquent class). Re-export everything from `types/models/index.ts`:

```
resources/js/types/
├── auth.ts           // starter-kit legacy — User type lives here (do not recreate)
├── global.d.ts       // ambient declarations + Pagination interfaces + per-model aliases
├── index.ts
├── vue-shims.d.ts
└── models/
    ├── index.ts
    ├── Order.ts
    └── OrderItem.ts
```

```ts
// resources/js/types/models/index.ts
export * from './Order';
export * from './OrderItem';
```

> **Note:** `User` does NOT get a `types/models/User.ts` — it stays in `types/auth.ts` (starter-kit legacy, uses `type`). Other new model types use the `interface`-based pattern below.

### 12.2 Base Type Definition — Use `interface`

Use `interface` for every new model type. The existing `types/auth.ts` (and other starter-kit files) still use `type` — leave them alone, the two coexist. New model types go in `types/models/` with `interface`.

```ts
// resources/js/types/models/Order.ts
export interface Order {
    id: number;
    name: string;
    status: OrderStatus;
    total: number;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // forward-compat for extra fields Laravel might add
}
```

Rules:

- Dates are always `string` (JSON has no Date type)
- Nullable columns → `string | null` (or the appropriate type)
- Soft deletes → add `deleted_at: string | null`
- Always include `[key: string]: unknown` so extra fields don't break TS

### 12.3 Relationships — Never on the Base Type

Relationships do NOT live on the base interface. They are inlined at the use site with an intersection:

```ts
// ❌ DON'T add teams to the base User type
export interface User {
    id: number;
    name: string;
    teams: Team[]; // ← never
}

// ✅ DO inline the relationship where it's used
defineProps<{
    users: Array<User & { teams: Team[] }>;
}>();
```

This keeps `User` representing only the model's own columns. The shape `User & { teams: Team[] }` is built per page.

### 12.4 Pagination — `global.d.ts`

Pagination interfaces live in `resources/js/types/global.d.ts` alongside the existing Vite / Inertia / Vue ambient declarations. Aliases follow the `{Model}Pagination` / `{Model}SimplePagination` naming convention.

```ts
// resources/js/types/global.d.ts (appended after the ambient declarations)

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}

export interface SimplePaginationMeta {
    current_page: number;
    from: number | null;
    path: string;
    per_page: number;
    to: number | null;
}

export interface SimplePaginatedResponse<T> {
    data: T[];
    meta: SimplePaginationMeta;
    links: {
        first: string | null;
        prev: string | null;
        next: string | null;
    };
}
```

Per-model aliases at the bottom of the same file:

```ts
// resources/js/types/global.d.ts (bottom)

import type { User } from '@/types/auth';
// (Once User is migrated to types/models/User.ts, import from there instead.)

type UserPagination = PaginatedResponse<User>;
type UserSimplePagination = SimplePaginatedResponse<User>;
```

Usage in pages:

```ts
defineProps<{
    users: UserPagination;
}>();

defineProps<{
    users: UserSimplePagination;
}>();
```

**Shape notes:**

- `PaginatedResponse.meta.links` is the **array of page controls** (1, 2, 3, …) Laravel renders for the pager UI.
- The top-level `PaginatedResponse.links` is the **first/last/prev/next navigation object** — a different shape.
- `SimplePaginationMeta` has no `last_page`, no `total`, and no `links` array — only current page info.
- `SimplePaginatedResponse.links` has only `first/prev/next` (no `last`).

### 12.5 Form Data

Use `Partial<T>` for create forms (no `id`, no timestamps yet). Use the full type for edit forms (model is hydrated):

```ts
defineProps<{ order: Order }>(); // edit — full record

const form = useForm<Partial<Order>>({
    // create — no id yet
    name: '',
    status: OrderStatus.Draft,
});

// For pagination forms
defineProps<{
    orders: PaginatedResponse<Order>;
    // or: OrderPagination if aliased in global.d.ts
}>();
```

### 12.6 Enum Constants — `lib/constants.ts`

Enum union types and their option arrays live in `resources/js/lib/constants.ts` — **not** in `types/`. Use `as const` so the runtime values are the single source of truth for the derived type:

```ts
// resources/js/lib/constants.ts
export const ORDER_STATUSES = ['draft', 'active', 'archived'] as const;
export type OrderStatus = (typeof ORDER_STATUSES)[number];

export const orderStatusOptions: Array<{ value: OrderStatus; label: string }> =
    [
        { value: 'draft', label: 'Draft' },
        { value: 'active', label: 'Active' },
        { value: 'archived', label: 'Archived' },
    ];
```

Usage in Vue:

```ts
import type { OrderStatus } from '@/lib/constants';
import { orderStatusOptions } from '@/lib/constants';

defineProps<{ order: { status: OrderStatus } }>();
```

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

When the source of truth lives in PHP (the `OrderStatus` enum from 11), keep both files in sync — add a new case to PHP and add a new value to the const array in lockstep.

### 12.7 Rules

- New model types: use `interface` in `resources/js/types/models/{Model}.ts`
- Exception: `User` lives in `types/auth.ts` (starter-kit legacy, uses `type`) — do not recreate in `types/models/`
- Existing types from the starter kit (`types/auth.ts`, `global.d.ts`, etc.): leave alone — they use `type`
- Re-export from `types/models/index.ts`
- Dates as `string`, nullable as `| null`, always include `[key: string]: unknown`
- Relationships NEVER on the base type — inline at use site with `T & { ... }`
- Pagination types live in `resources/js/types/global.d.ts` — not in a separate `types/models/Paginated.ts` file
- Use the standard Laravel shapes: `PaginationLink`, `PaginationMeta` (includes `links: PaginationLink[]`), `PaginatedResponse<T>`, `SimplePaginationMeta`, `SimplePaginatedResponse<T>`
- Per-model pagination aliases follow `{Model}Pagination` / `{Model}SimplePagination` naming, declared at the bottom of `global.d.ts`
- Use `Partial<T>` for create forms, full `T` for edit forms
- Enum constants: in `resources/js/lib/constants.ts`, never in `types/`
- Use `as const` to derive the type from runtime values

---

## 13. Error Handling

### 13.1 Development Mode

In development, non-Inertia responses (like stack traces) are automatically shown in a modal.

### 13.2 Production Error Pages

For production, configure proper Inertia error responses:

```php
// app/Providers/AppServiceProvider.php
use Inertia\Inertia;

public function boot(): void
{
    Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
        if (in_array($response->statusCode(), [403, 404, 500, 503])) {
            return $response->render('ErrorPage', [
                'status' => $response->statusCode(),
            ])->withSharedData();
        }
    });
}
```

### 13.3 CSRF Token Mismatches

Handle CSRF mismatches gracefully in `bootstrap/app.php`:

```php
use Symfony\Component\HttpFoundation\Response;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response $response) {
        if ($response->getStatusCode() === 419) {
            return back()->with([
                'message' => 'The page expired, please try again.',
            ]);
        }

        return $response;
    });
});
```

---

## 14. Page Header Layout

### 14.1 Standard Index Page Layout

Index pages should follow a consistent vertical stack:

1. **Title** — standalone heading
2. **Toolbar row** — search/filters on the left, primary action on the right
3. **Data table** — results
4. **Pagination** — bottom of table

The project ships a `PageHeader` wrapper (`@/components/PageHeader.vue`) that owns the title + description + toolbar layout. Use it; don't hand-roll `h1` + toolbar `div` for index pages:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/core/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ItemCreateModal } from '@/components/items/ItemCreateModal.vue';

const props = defineProps<{ items: Paginated<Item> }>();

const nameFilter = ref('');
const hasActiveFilters = computed(() => nameFilter.value !== '');

function applyFilters() {
    router.get(
        items.index().url,
        { 'filter[name]': nameFilter.value },
        { preserveState: true, replace: true, only: ['items'] },
    );
}

const debouncedApplyFilters = (() => {
    let t: ReturnType<typeof setTimeout> | null = null;
    return () => {
        if (t) clearTimeout(t);
        t = setTimeout(applyFilters, 300);
    };
})();

watch(nameFilter, debouncedApplyFilters);

function clearFilters() {
    nameFilter.value = '';
}
</script>

<template>
    <div class="space-y-3 p-4 lg:p-6">
        <PageHeader title="Items" description="Manage your items.">
            <template #actions>
                <ItemCreateModal v-if="canManageItems" />
            </template>

            <template #search>
                <div class="relative w-full sm:w-64 sm:max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="nameFilter"
                        type="search"
                        placeholder="Search by name…"
                        class="pl-8"
                    />
                </div>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                >
                    <X class="mr-1 h-3.5 w-3.5" />
                    Clear
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="items.data"
            row-key="id"
        />

        <Pagination :data="items" />
    </div>
</template>
```

**Rules:**

- Use the `PageHeader` component for the title/toolbar region. It owns the `h1`, the description, the `#actions` slot (right side), and the `#search` slot (left side).
- Use the `DataTable` component (`@/components/core/DataTable.vue`) for tables — wraps shadcn-vue `Table`/`TableHeader`/`TableBody`/`TableRow`/`TableHead`/`TableCell`/`TableEmpty` with sortable columns and an empty state.
- Use the `Pagination` component (`@/components/Pagination.vue`) for pagination — wraps the Laravel paginator shape.
- Use the `Input` component with an absolutely-positioned `Search` icon for search fields. shadcn-vue has no `icon` prop; position the icon manually with `pl-8` and a centered `absolute` icon.
- Icons come from `@lucide/vue` — never `i-lucide-*` string props. Import the icon as a named export and render it as a component (often with `class="h-4 w-4"` or `class="h-3.5 w-3.5"`).
- Buttons render label content as a slot, not a `label` prop. `variant` accepts `default`, `destructive`, `outline`, `secondary`, `ghost`, `link`. `size` accepts `default`, `xs`, `sm`, `lg`, `icon`, `icon-xs`, `icon-sm`, `icon-lg`.

---

## 15. Breadcrumb Pattern

### 15.1 Page Breadcrumb Structure

Every full-page view (not modals) should set breadcrumbs using `setLayoutProps()`:

```vue
<script setup lang="ts">
import { setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import dashboard from '@/routes/dashboard';
import items from '@/routes/items';

defineOptions({ layout: AppLayout });

setLayoutProps({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard.index().url },
        { title: 'Items', href: items.index().url },
        { title: 'Edit Item', href: '#' },
    ],
});
</script>
```

> **Shape:** each breadcrumb entry has a `title` (string) and `href` (`InertiaLinkProps['href']`). The project's `BreadcrumbItem` type is in `@/types/navigation.ts` and re-exports with the shape the layout expects.

### 15.2 Layout Receives Breadcrumbs

The layout forwards `breadcrumbs` to `AppSidebarHeader`, which delegates to the shared `Breadcrumbs` component (`@/components/Breadcrumbs.vue`). That component composes the shadcn-vue `Breadcrumb` primitives:

```vue
<!-- AppLayout.vue -->
<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <slot :breadcrumbs="breadcrumbs" />
</template>
```

```vue
<!-- Breadcrumbs.vue (shared, already in the project) -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
```

> **Don't render `Breadcrumb` primitives yourself in pages.** Use the shared `Breadcrumbs` component so the layout (e.g. `CoreLayout`) controls placement in the sidebar header.

### 15.3 Breadcrumb Hierarchy Reference

| Page             | Breadcrumbs                                            |
| ---------------- | ------------------------------------------------------ |
| Dashboard        | Home                                                   |
| Categories       | Home → Categories                                      |
| Items            | Home → Items                                           |
| Items → Edit     | Home → Items → Edit Item (last entry has no link)      |
| Items → Comments | Home → Items → [Name] → Comments                      |
| Items → Tags     | Home → Items → [Name] → Tags                           |
| Tags             | Home → Tags                                            |
| Tags → Items     | Home → Tags → [Name] → Items                           |

The last entry renders as a `<BreadcrumbPage>` (current page, non-link). Every other entry has a real `href` so the layout can wrap it in an Inertia `<Link>`. Use `href: '#'` for the current page placeholder if you need the type to be happy, or build the array without the last item if your layout defaults it.

### 15.4 Which Pages Need Breadcrumbs?

- **Full pages:** `Index.vue`, `Show.vue`, etc. — use `setLayoutProps()` and `defineOptions({ layout: AppLayout })`
- **Edit modals:** `Edit.vue` opened via `visitModal()` — do NOT use layout or breadcrumbs

---

## 16. Testing

### 16.1 Testing Pages with Inertia Assertions

```php
$response = $this->get('/items');

$response->assertInertia(fn (Assert $page) => $page
    ->has('items')
    ->where('items.data.0.name', 'Sample Item')
);
```

### 16.2 Testing Deferred Props

```php
$response->assertInertia(fn (Assert $page) => $page
    ->has('items')
    ->missing('categories')  // Deferred prop not in initial response
    ->loadDeferredProps(fn (Assert $reload) => $reload
        ->has('categories')
    )
);
```

### 16.3 Testing Specific Deferred Groups

```php
$response->assertInertia(fn (Assert $page) => $page
    ->loadDeferredProps('attributes', fn (Assert $reload) => $reload
        ->has('teams')
        ->has('projects')
    )
);
```

---

## 17. Quick Reference

### 17.1 Import Summary

```ts
// Core Inertia
import {
    router,
    useForm,
    usePage,
    Link,
    setLayoutProps,
} from '@inertiajs/vue3';

// Modals
import { visitModal } from '@inertiaui/modal-vue';

// Deferred (if needed)
import { Deferred } from '@inertiajs/vue3';
```

### 17.2 Common Patterns

```vue
<script setup lang="ts">
// Layout setup
import AppLayout from '@/layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });

// Breadcrumbs
import { setLayoutProps } from '@inertiajs/vue3';
setLayoutProps({ breadcrumbs: [...] });

// Navigation
import { router } from '@inertiajs/vue3';
router.visit(url);
router.get(url, params, { preserveState: true });

// Forms
import { useForm } from '@inertiajs/vue3';
const form = useForm({ name: '', price: 0 });
form.post(url, { onSuccess: () => {} });
</script>
```

---

## 18. Key Differences from Inertia v2

| Feature           | v2                             | v3                                     |
| ----------------- | ------------------------------ | -------------------------------------- |
| Layout definition | `layout: Layout` in export     | `defineOptions({ layout: Layout })`    |
| Layout props      | Via page component props       | `setLayoutProps()` in `<script setup>` |
| `Inertia::lazy()` | Available                      | Removed — use `Inertia::optional()`    |
| `router.cancel()` | Available                      | Removed — use `router.cancelAll()`     |
| Form reset timing | Resets immediately on response | Resets in `onFinish` callback          |
| Future config     | `future` namespace             | All v2 futures always enabled          |
| Event names       | `exception`, `invalid`         | `networkError`, `httpException`        |

---

## 19. SSR Support

Inertia v3 SSR works automatically in Vite dev mode with the `@inertiajs/vite` plugin — no separate Node.js server needed during development.

For production SSR error handling:

```php
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\SsrRenderFailed;

Event::listen(SsrRenderFailed::class, function (SsrRenderFailed $event) {
    Log::warning('SSR failed', $event->toArray());
});
```

---

## 20. HTTP Requests

### 20.1 The Rule

**Never use `axios` or `fetch` for HTTP requests in this app.** Both require manual CSRF token handling, lack Inertia's reactive state, and force you to re-implement progress tracking, validation-error parsing, and cancellation.

For any HTTP call that doesn't trigger an Inertia page visit, use the `useHttp` hook from `@inertiajs/vue3`. It:

- Auto-handles CSRF tokens (Laravel's `XSRF-TOKEN` cookie + headers)
- Returns the same reactive state shape as `useForm` (`errors`, `processing`, `progress`, `hasErrors`, `wasSuccessful`, `recentlySuccessful`, `isDirty`)
- Auto-parses 422 validation errors into `errors`
- Auto-sends `multipart/form-data` for file uploads with `progress.percentage`
- Supports cancellation via `cancel()`
- Returns a Promise that resolves with the parsed JSON response

Use it for: external APIs, typeahead/search endpoints, file uploads, any non-Inertia Laravel endpoint.

Don't use it for: anything that should trigger an Inertia page visit (use `router.get`, `router.post`, or `useForm` instead — see 4.1).

### 20.2 Basic Usage

```ts
import { useHttp } from '@inertiajs/vue3';

const http = useHttp({
    query: '',
});

function search() {
    http.get('/api/search', {
        onSuccess: (data) => {
            console.log(data); // parsed JSON response
        },
    });
}
```

```vue
<input v-model="http.query" @input="search" />
<div v-if="http.processing">Searching...</div>
```

### 20.3 Methods

| Method                              | Use for                                              |
| ----------------------------------- | ---------------------------------------------------- |
| `http.get(url, options)`            | Fetching data, search/typeahead                      |
| `http.post(url, options)`           | Creating resources that don't return an Inertia page |
| `http.put(url, options)`            | Full update via JSON endpoint                        |
| `http.patch(url, options)`          | Partial update via JSON endpoint                     |
| `http.delete(url, options)`         | Deleting via JSON endpoint                           |
| `http.submit(method, url, options)` | Dynamic method when the verb is computed             |

All return `Promise<TResponse>` where `TResponse` is the inferred JSON shape.

### 20.4 Reactive State

Same as `useForm`:

```ts
const http = useHttp({ name: '', email: '' });

http.name; // current value (reactive)
http.email; // current value (reactive)
http.errors.name; // first validation error for "name"
http.hasErrors; // boolean
http.processing; // true while request is in flight
http.progress; // { percentage, total } | null  (file uploads)
http.wasSuccessful; // true after a successful response
http.recentlySuccessful; // true for 2s after success
http.isDirty; // true if data differs from defaults
```

Bind directly to inputs:

```vue
<input v-model="http.name" />
<div v-if="http.errors.name">{{ http.errors.name }}</div>
<button :disabled="http.processing">Save</button>
```

### 20.5 Validation Errors

422 responses are auto-parsed into `http.errors`:

```vue
<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import FormControl from '@/components/FormControl.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const http = useHttp({ name: '', email: '' });

function save() {
    http.post('/api/users');
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <FormControl
            name="name"
            label="Name"
            :error="http.errors.name"
            v-slot="{ id }"
        >
            <Input
                :id="id"
                v-model="http.name"
                :aria-invalid="!!http.errors.name"
            />
        </FormControl>

        <FormControl
            name="email"
            label="Email"
            type="email"
            :error="http.errors.email"
            v-slot="{ id }"
        >
            <Input
                :id="id"
                v-model="http.email"
                type="email"
                :aria-invalid="!!http.errors.email"
            />
        </FormControl>

        <Button type="submit" :disabled="http.processing">Save</Button>
    </form>
</template>
```

### 20.6 File Uploads

Files in the data auto-trigger `multipart/form-data` and populate `progress`:

```vue
<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

const http = useHttp<{ file: File | null }, { url: string }>({
    file: null,
});

function upload() {
    http.post('/api/uploads', {
        onSuccess: (data) => console.log('Uploaded to', data.url),
    });
}
</script>

<template>
    <input type="file" @change="http.file = $event.target.files[0]" />
    <Progress
        v-if="http.progress"
        :model-value="http.progress.percentage"
    />
    <Button :disabled="http.processing" @click="upload">Upload</Button>
</template>
```

### 20.7 Cancellation

```ts
http.get('/api/slow-endpoint', {
    onCancel: () => console.log('cancelled'),
});

// ...later, on unmount or user action:
http.cancel();
```

Useful for: typeahead (cancel pending request when user types again), uploads (let user cancel long uploads), search-as-you-type.

### 20.8 Multiple Instances

Create separate `useHttp` instances for independent state:

```ts
const search = useHttp({ query: '' });
const upload = useHttp({ file: null });
```

`search.processing` and `upload.processing` track independently.

### 20.9 Rules

- **Never** import `axios` or use the global `fetch` for app HTTP calls
- **Always** use `useHttp` for non-Inertia requests
- URL paths can be hardcoded (`/api/search`) or come from Wayfinder actions — same DX either way
- Use one `useHttp` instance per logical request flow (don't reuse for unrelated requests)
- Type the request/response with generics: `useHttp<RequestShape, ResponseShape>(...)`
- For endpoints that should trigger an Inertia visit, use `useForm` or `router.*` instead
  (End of file - total 565 lines)
