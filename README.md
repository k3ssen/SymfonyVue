# SymfonyVueBundle

A small SymfonyBundle that makes it easy to combine Twig and Vue.

Instead of using cumbersome `data-*` attributes or creating an API-endpoint for just about anything,
this bundle lets you pass data to vue by calling `{{ vue_data('someVariable', someObjectOrValue) }}`
in Twig. Form data can be used in Vue by using the `v_model` form-option. 
See [Usage](#usage) for more details.

**Supported versions**  
* PHP 7.4 and 8
* Symfony 4.4, 5 and 6  
  Older versions may work, but these haven't checked as they are no longer maintained.
* Vue 2 and 3  
  This bundle offers mainly backend-logic, which isn't constrained by the Vue version. 
  These different versions require slightly different code to make this bundle work. More on that is documented below. 

## Setup - quickstart

Install the bundle:
`composer require k3ssen/symfony-vue-bridge`

You'll probably want to use setup VueJs with Symfony Encore 
(see [Setup with Encore](#setup-with-encore) for details below), but for quickly trying out this bundle
you can use the include-script below in your Twig code (replace the `3` with a `2` if you want to use version 2):
```
{% include '@SymfonyVue/_vue3_script.html.twig' %}
```

This will activate vue on the element with `id="app"`, so you'll need an element that has this id set on
it. 

### Example
If you use 
[Symfony's MakerBundle](https://symfony.com/bundles/SymfonyMakerBundle/current/index.html) 
to run `php bin/console maker:controller Dashboard` you should have an 
`template/dashboard/index.html.twig` file. You can replace the body block with the following:
```
{% block body %}
    <div id="app">
        {{ vue_data('count', 1) }}
        <button @click="count++" v-text="'Counter: ' + count"></button>
    </div>
    {% include '@SymfonyVue/_vue3_script.html.twig' %}
{% endblock %}
```

This will result in a button that increments the counter once you click on it.

## Setup with Encore
You can find elaborate information on Symfony's guides for installing
[Encore](https://symfony.com/doc/current/frontend/encore/installation.html)
and [Enabling Vue](https://symfony.com/doc/current/frontend/encore/vuejs.html).

### 1. Install encore
 `composer require symfony/webpack-encore-bundle`


### 2. Enable Vue.js
Enable Vue in `webpack.config.js`:
```js
    // ...
    Encore
        // ...
        .enableVueLoader(() => {}, {
            runtimeCompilerBuild: true,
            useJsx: true
        })
    // ...
```

> **Tips:**
> * You'll probably want to also uncomment the `// enableSassLoader` in webpack.config.js to use scss
>   (which can be used in vue-components as well).
> * If you want to use Typescript, you should also uncomment `//.enableTypeScriptLoader()`
>    * Make sure to rename `assets/app.js` to `assets/app.js` to prevent some
>      [nasty exceptions during yarn watch](https://stackoverflow.com/questions/67925815/cannot-find-module-in-node-modules-folder-but-file-exist)

### 3. Install assets
Run `yarn install` followed by  `yarn dev`. 
You may see errors that you need to install some additional packages.
You can follow these instructions and re-run `yarn dev` until done.

> **Note:** As of writing, Encore suggests
> installing vue@^2.5 with the appropriate loader. If you want to use Vue 3 you should
> remove or use different version constraints for vue packages.

### 4. Twig vue-javascript setup
The serverside data must be passed to vue, for which you can use the global `window` object.
For example, you can add the following code in your `base.html.twig`:
```
<div id="app">
    {% block body %}{% endblock %}
</div>
<script>
    window.vueData = {{ get_vue_data() | raw }};
    window.vueStoreData = {{ get_vue_store() | raw }};
</script>
```

The following things are relevant:
* the content you want to use Vue for is wrapped inside an element with the "app" id.
* `window.vueData` and `window.vueStoreData` must be created AFTER your content.
 (using `vue_add()` after this code won't have any effect).
* `window.vueData` and `window.vueStoreData` must be created BEFORE the app.js is loaded
  Encore uses `defer` on the script-tag by default, in which case this should work correctly.

### 5. Create Vue instance
Finally, a vue-instance that uses this data must be created.
Open your `assets/app.js` to add some code (see below).


**Vue2**  
```
import Vue from 'vue';

const vue = window.vue ?? {};

// Read the data of the already existing vue-object into a variable.
const vueObjectData = typeof vue.data === 'function' ? vue.data(): (vue.data ?? {});
// Merge the vueData with the already existing vueObjectData
vue.data = () => Object.assign(window.vueData ?? {}, vueObjectData);

// Uncomment line below to use different default delimiters (only applies to the global vue-object you use in Twig)
// vue.delimiters ??= ['${', '}$'];

// Create a reactive global $store variable that can be used in all components.
Vue.prototype.$store = Vue.observable(window.vueStoreData ?? {});

vue.el ??= '#app';
new Vue(vue);
```

**Vue3**
```
import { createApp, reactive  } from 'vue';

const vue = window.vue ?? {};

// Read the data of the already existing vue-object into a variable.
const vueObjectData = typeof vue.data === 'function' ? vue.data(): (vue.data ?? {});
// Merge the vueData with the already existing vueObjectData
vue.data = () => Object.assign(window.vueData ?? {}, vueObjectData);

// Uncomment line below to use different default delimiters (only applies to the global vue-object you use in Twig)
// vue.delimiters ??= ['${', '}$'];

const app = createApp(vue);

// Create a reactive global $store variable that can be used in all components.
app.config.globalProperties.$store = reactive(window.vueStoreData ?? {});

app.mount(vue.el ?? '#app');
```


**Typescript**  
If you're using Typescript, you should edit `app.ts` instead. 
You can use similar code, but need to make some changes to make the compiler happy.

## Usage

### Using a global Vue-object

Complex Vue-logic should be written in Vue-components, but there are times when you want to do
relatively simple Vue stuff inside Twig without any hassle.

By using a global object, this becomes fairly easy:
```
{% extends 'base.html.twig' %}

{% block body %}
    <div id="app">
        <h1>Seconds passed: ${ seconds }$</h1>
        <p v-if="seconds > 5">
            More than 5 seconds have passed.
        </p>
    </div>
    <script>
        vue = {
            delimiters: ['${', '}$'],
            data: () => ({
                seconds: 0,
            }),
            mounted() {
                setInterval(() => this.seconds++, 1000);
            },
        }
    </script>
{% endblock %}
```


### Passing server-side data to Vue

When you want to pass server-side data like an entity to Vue, you'd need something like this:
```
<script>
    vue = {
        data: () => ({
            someObject: {{ someObject | json_encode | raw }},
        })
    }
</script>
```

This works fine, but this has a bit too much boilerplate for simple scenario's where you only need to make data available.

To make this simple and more concise, this bundle adds these Twig functions:

* `vue_add('someObject', someObject)`  
   In practise this has the same effect as the code above.
* `vue_store('someObject', someObject)`  
   To create a global `$store.someObject` variable than can be accessed in all components.
* `vue('someObject', someObject)`  
   Does the same as vue_add, but it returns the key, so you can use this directly as a property.
* `someObject|vue`  
   A twig-filter that is similar to the vue-function, but it allows you to omit the key-name for objects.


### Using v_model in forms

A typical case is that you have a form in which you want some Vue-logic.

This bundle adds a `VueFormTypeExtension` that provides a `v_model` option, which makes it
really easy to have a `v-model` added to your form-field and make the data available to vue.

For example, your controller action could contain the following code to build a form:
```
$form = $this->createForm(TextType::class, null, [
    'v_model' => 'name',
]);
```
Then in Twig you can use the `name` variable as Vue-data.
```
{{ form_start(form) }}
{{ form_widget(form) }}
<button :disabled="!name">Submit</button>
{{ form_end(form) }}
```

### Other server-side data

If you have other data that you want to make available to Vue you can use 
the `VueDataStorage` service. 

It ultimately boils down to an array inside this service that is converted to a
json object that can be used by Vue.
