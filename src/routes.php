<?php
Route::get('packer',function() {
	// Class
	//$packer = new \Angel\Packer\Packer(array('debug' => 1));
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
	
	print "packages:<pre>";
	print_r($packages);
});