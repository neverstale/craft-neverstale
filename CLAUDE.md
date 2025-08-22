# Neverstale Plugin Development Notes

## Craft CMS Slideout Implementation Guide

Based on: https://dev-diary.newism.com.au/posts/creating-craft-cms-slideouts.html

### Overview
Craft CMS slideouts provide a consistent UI pattern for forms and detailed views that slide in from the right side of the screen. They use the `Craft.CpScreenSlideout` class and require specific setup patterns.

### Required Components (per article)

#### 1. Asset Bundle Setup
Create and register an asset bundle to load your JavaScript:

```php
class MyModuleAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@modules/mymodule/resources';
        $this->depends = [CpAsset::class];
        $this->js = ['mymodule.js'];
        parent::init();
    }
}
```

#### 2. JavaScript Widget Pattern
The article recommends using Garnish.Base.extend for creating slideout triggers:

```javascript
window.MyModuleSlideoutTrigger = Garnish.Base.extend({
    init: function (elementId) {
        this.$triggerElement = $('#' + elementId);
        this.$triggerElement.on('click', $.proxy(this, 'onClick'));
    },
    onClick: function () {
        const slideout = new Craft.CpScreenSlideout('my-module/slideout/content');
        slideout.open();
        slideout.on('submit', function (e) {
            // Handle submit response
            alert(JSON.stringify(e.response.data));
        })
    },
});
```

#### 3. Controller Structure
The article shows a two-action pattern:

```php
class SlideoutController extends Controller
{
    // Initial page that contains the trigger
    public function actionIndex(): Response
    {
        $this->requireCpRequest();
        $view = $this->getView();
        $view->registerAssetBundle(MyModuleAsset::class);
        
        return $this->renderTemplate('my-module/slideout/index');
    }
    
    // Slideout content handler
    public function actionContent(): Response
    {
        $this->requireCpRequest();
        
        // Handle POST submission
        if ($this->request->getIsPost()) {
            return $this->asSuccess('Success!', [
                'message' => 'Form was submitted'
            ]);
        }
        
        // Return slideout screen for GET
        return $this->asCpScreen()
            ->title('My Slideout')
            ->action('my-module/slideout/content')
            ->contentTemplate('my-module/slideout/content');
    }
}
```

#### 4. Template Integration
Initialize the JavaScript widget in your Twig template:

```twig
{% js %}
    new MyModuleSlideoutTrigger('trigger-button-id');
{% endjs %}
```

### Key Implementation Points from Article

1. **Registration Requirements**:
   - Register template roots in module/plugin
   - Register CP routes
   - Register asset bundle

2. **JavaScript Initialization**:
   - Use Garnish.Base.extend for creating widgets
   - Initialize widgets after DOM is ready
   - Use jQuery proxy for proper context binding

3. **Event Handling**:
   - The slideout fires a 'submit' event with response data
   - Access response data via `e.response.data`
   - Handle both success and error states

4. **Form Submission**:
   - Controller action handles both GET (display) and POST (submit)
   - Use `$this->asSuccess()` for successful submissions
   - Return data in the second parameter for JavaScript access

### Article's Best Practices

1. **Modular Approach**: Keep slideout logic in separate controllers
2. **Event-Driven**: Use event listeners for slideout interactions
3. **Consistent UI**: Use `asCpScreen()` for proper Craft styling
4. **Asset Organization**: Bundle slideout-specific JS in asset bundles

## Running Tests and Linting

Before committing changes, run:
```bash
# PHP linting (if configured)
composer run-script lint

# JavaScript linting (if configured)
npm run lint
```

## Key Files

- `/src/controllers/FlagController.php` - Slideout controller actions
- `/src/templates/_slideouts/ignore-flag.twig` - Slideout template
- `/src/resources/js/neverstale.js` - Main JavaScript file
- `/src/resources/js/ignore-flag-slideout.js` - Slideout-specific JavaScript
- `/src/assets/NeverstaleAsset.php` - Asset bundle configuration