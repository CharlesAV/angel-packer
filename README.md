Angel Packer
==============
This package offers a very basic version of 3D bin packing. It will 'pack' an array of items into an array of available boxes as efficently as possible.  It's algorithm, however, is very basic, largely using overall dimensions and volume to pack item (as opposed to determing if they can actually fit in relation to other items in the same box).

It was built for use in the [Angel CMS](https://github.com/JVMartin/angel), but works independently of that as well.

Installation
------------
Add the following requirements to your `composer.json` file:
```javascript
"require": {
	"angel/packer": "dev-master"
},
```

Issue a `composer update` to install the package.

Add the following service provider to your `providers` array in `app/config/app.php`:
```php
'Angel\Packer\PackerServiceProvider'
```

Use
------------

```php
// Class
$packer = App::make('Packer');

// Boxes we can pack in     
$boxes = array(
	array(
		'dimensions' => array(
			12,
			8,
			4
		),
		'weight' => 1, // Optional, the weight of the box material
		'weight_max' => 15, // Optional, the max weight it can handle (including the box material weight)
		'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
	),
	array(
		'dimensions' => array(
			20,
			10,
			2
		),
		'weight' => 1.5,
		'weight_max' => 25,
		'key' => 'def'
	)
);
$packer->boxes($boxes);

// Items we need to pack
$items = array(
	array(
		'dimensions' => array(
			6,
			5,
			2
		),
		'weight' => 3, // Optional
		'quantity' => 2,
		'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
	),
	array(
		'dimensions' => array(
			10,
			4,
			4
		),
		'weight' => 5,
		'quantity' => 1,
		'key' => 'def'
	),
	array(
		'dimensions' => array(
			14,
			6,
			2
		),
		'weight' => 8,
		'quantity' => 1,
		'key' => 'ghi'
	)
);
$packer->items($items);

// Pack
$packages = $packer->pack();
```

The array that's returned by the pack() method will contain information on the package(s) we packed the items into including the box size of the package, what items are in it, the total quantity, total volume, total weight, etc.  An example:

```php
Array
(
    [0] => Array
        (
            [box] => Array
                (
                    [dimensions] => Array
                        (
                            [0] => 20
                            [1] => 10
                            [2] => 2
                        )

                    [weight] => 1.5
                    [weight_max] => 25
                    [key] => def
                    [volume] => 400
                    [x] => 1
                )

            [weight] => 15.5
            [volume] => 288
            [quantity] => 3
            [items] => Array
                (
                    [0] => Array
                        (
                            [dimensions] => Array
                                (
                                    [0] => 14
                                    [1] => 6
                                    [2] => 2
                                )

                            [weight] => 8
                            [quantity] => 1
                            [key] => ghi
                            [volume] => 168
                            [x] => 2
                            [quantity_packed] => 0
                        )

                    [2] => Array
                        (
                            [dimensions] => Array
                                (
                                    [0] => 6
                                    [1] => 5
                                    [2] => 2
                                )

                            [weight] => 3
                            [quantity] => 2
                            [key] => abc
                            [volume] => 60
                            [x] => 0
                            [quantity_packed] => 0
                        )

                )

        )

    [1] => Array
        (
            [box] => Array
                (
                    [dimensions] => Array
                        (
                            [0] => 12
                            [1] => 8
                            [2] => 4
                        )

                    [weight] => 1
                    [weight_max] => 15
                    [key] => abc
                    [volume] => 384
                    [x] => 0
                )

            [weight] => 6
            [volume] => 160
            [quantity] => 1
            [items] => Array
                (
                    [1] => Array
                        (
                            [dimensions] => Array
                                (
                                    [0] => 10
                                    [1] => 4
                                    [2] => 4
                                )

                            [weight] => 5
                            [quantity] => 1
                            [key] => def
                            [volume] => 160
                            [x] => 1
                            [quantity_packed] => 0
                        )

                )

        )

)
```

Configuration
------------
There are a few things you can configure in regards to how we 'pack' items.  To tweak these configurations, first issue the following command to publish the config file:

```bash
php artisan config:publish angel/packer       # Publish the config
```

Then, open up your `app/config/packages/angel/packer/config.php` where you'll find the following configurable variables:
```php
array(
	'volume_pad' => 1.2 // Padding to add to volume since we're not actually bin packing it. Value is multiplier of original volume so 1 = no padding.
);
```

**volume_pad**

Since we're not actually bin packing items (rearranging them in a 3d space to see if they'll fit together), we found that adding a little 'padding' to the combined volume we're checking against when filling a box helps to offset some of the erronious packing.  This configuration value is how much we multiply the combined volume by when adding more than 1 item to a box.  By default, it's 1.2 (so, we're basically adding 20% padding).  A value of 1 would mean no padding.