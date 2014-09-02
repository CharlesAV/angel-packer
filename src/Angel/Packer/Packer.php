<?php
namespace Angel\Packer;

use Config;

if(!class_exists('Packer')) {
	/**
	 * Handles packaging of items into box(es).	
	 *
	 * Note, this assumes all weights and all dimensions (of the box and items we're packing into it) are the same unit.		
	 */
	class Packer  {
		/** Holds the array of boxes available to pack items into. */
		public $boxes = array();
		/** Holds the array of items we want to pack into box(es). */
		public $items = array();
		/** Holds an array of logging information, including errors. */
		public $log = array();
		/** An array of configuration values. */
		public $c = array(
			'dimensions_check' => 1, // Check against overall dimensions when packing.
			'volume_pad' => 1.2, // Padding to add to volume since we're not actually bin packing it. Value is multiplier of original volume so 1 = no padding.
			'debug' => 0, // Debug - will 'print' info throughout the process if set to 1
		);
		
		/**
		 * Constructs the class.
		 *
		 * @param array $c An array of configuration values. Default = NULL
		 */
		function __construct($c = NULL) {
			self::Packer($c);
		}
		function Packer($c = NULL) {
			// Config
			if($v = Config::get('packer::volume_pad')) $this->c['volume_pad'] = $v;
			if($c) {
				foreach($c as $k => $v) {
					if($k == "boxes") $this->boxes($v);
					else if($k == "box") $this->box($v);
					else if($k == "items") $this->items($v);
					else if($k == "item") $this->item($v);
					else $this->c[$k] = $v;
				}
			}
		}
		
		/**
		 * Adds an array of boxes to the available box sizes.
		 *
		 * Example:
		 * $boxes = array(
		 *		array(
		 *			'dimensions' => array(
		 *				12,
		 *				8,
		 *				4
		 *			),
		 *			'weight' => 1, // Optional, the weight of the box material
		 *			'weight_max' => 15, // Optional, the max weight it can handle (including the box material weight)
		 *			'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
		 *		),
		 *		array(
		 *			'dimensions' => array(
		 *				20,
		 *				10,
		 *				2
		 *			),
		 *			'weight' => 1.5,
		 *			'weight_max' => 25,
		 *			'key' => 'def'
		 *		)
		 * );
		 * $packer->boxes($boxes);
		 *
		 * @param array $boxes An array of boxes to add to the available box sizes.
		 */
		function boxes($boxes) {
			foreach($boxes as $box) {
				$this->box($box);
			}
		}
		
		/**
		 * Adds a single box to the available box sizes.
		 *
		 * Example:
		 * $box = array(
		 *		'dimensions' => array(
		 *			12,
		 *			8,
		 *			4
		 *		),
		 *		'weight' => 1, // Optional, the weight of the box material
		 *		'weight_max' => 15, // Optional, the max weight it can handle
		 *		'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
		 * );
		 * $packer->box($box);
		 *
		 * @param array $box An array of the single box's dimensions and weight.
		 */
		function box($box) {
			// Volume
			$box['volume'] = $box['dimensions'][0] * $box['dimensions'][1] * $box['dimensions'][2];
			
			// X
			$box['x'] = count($this->boxes);
			
			// Box
			$this->boxes[] = $box;
		}
		
		/**
		 * Adds an array of items to the list of items we want to pack into box(es)
		 *
		 * Example:
		 * $items = array(
		 *		array(
		 *			'dimensions' => array(
		 *				6,
		 *				5,
		 *				2
		 *			),
		 *			'weight' => 3, // Optional
		 *			'quantity' => 2,
		 *			'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
		 *		),
		 *		array(
		 *			'dimensions' => array(
		 *				10,
		 *				4,
		 *				4
		 *			),
		 *			'weight' => 5,
		 *			'quantity' => 1,
		 *			'key' => 'def'
		 *		)
		 * );
		 * $packer->items($items);
		 *
		 * @param array $items An array of items to add to the list of items we want to pack.
		 */
		function items($items) {
			foreach($items as $item) {
				$this->item($item);
			}
		}
		
		/**
		 * Adds a single item to the list of items we want to pack into box(es).
		 *
		 * Example:
		 * $item = array(
		 *		'dimensions' => array(
		 *			6,
		 *			5,
		 *			2
		 *		),
		 *		'weight' => 3, // Optional
		 *		'quantity' => 2,
		 *		'key' => 'abc' // Optional, just makes it easier for you to reference it when we return array of packed boxes/items
		 * );
		 * $packer->item($item);
		 *
		 * @param array $item An array of the single item's dimensions and weight.
		 */
		function item($item) {
			// Quantity
			if(!$item['quantity']) $item['quantity'] = 1;
			
			// Volume
			$item['volume'] = $item['dimensions'][0] * $item['dimensions'][1] * $item['dimensions'][2];
			
			// X
			$item['x'] = count($this->items);
			
			// Store
			$this->items[] = $item;
		}
		
		/**
		 * Packs the list of items into the available box sizes, returning the most efficent (fewest and smallest) boxes.
		 *
		 * @return array An array of boxes we packed items into and what items are packed in each of them.
		 */
		function pack() {
			// Error
			if(!$this->boxes or !$this->items) {
				$this->error("There are no ".(!$this->boxes ? "boxes to pack items in" : "items to pack").".");
				return;
			}
			
			// Sort boxes - smallest first
			usort($this->boxes,array('\Angel\Packer\Packer','sort'));
			$this->log("Boxes: ".$this->return_array($this->boxes));
			
			// Sort items - largest first
			usort($this->items,array('\Angel\Packer\Packer','rsort'));
			$this->log("Items: ".$this->return_array($this->items));
			
			// Packages
			$packages = NULL;
			$debug_limit = 100;
			$debug_counter = 0;
			while(true) {
				// Items to pack?
				$items = 0;
				foreach($this->items as $item_k => $item) {
					if(!isset($item['quantity_packed'])) $item['quantity_packed'] = 0;
					if($item['quantity_packed'] < $item['quantity']) {
						$items = 1;
						break;
					}
				}
				if($items) {
					// Log
					$this->log(" ","html");
					$this->log("<b>Attempt ".($debug_counter + 1)."</b>");
			
					// Pack
					$package = NULL;
					$packages_temp = NULL;
					foreach($this->boxes as $box_k => $box) {
						$this->log("<em>Box ".$box_k." (volume: ".$box['volume'].", width: ".$box['dimensions'][0].", length: ".$box['dimensions'][1].", height: ".$box['dimensions'][2].", max weight: ".$box['weight_max'].")</em>");
					
						$package_temp = array('box' => $box,'weight' => $box['weight'],'volume' => 0,'quantity' => 0);
						$package_temp_items_unpacked = 0;
						foreach($this->items as $item_k => $item) {
							if(!isset($item['quantity_packed'])) $item['quantity_packed'] = 0;
							$quantity = $item['quantity'] - $item['quantity_packed'];
							if($quantity) {
								for($x = 1;$x <= $quantity;$x++) {
									// Fits?
									if($this->fits($package_temp,$item)) {
										// Item
										$item['quantity'] = (isset($package_temp['items'][$item_k]['quantity']) ? ($package_temp['items'][$item_k]['quantity'] + 1) : 1);
										$package_temp['items'][$item_k] = $item;
										// Volume
										$package_temp['volume'] += $item['volume'];
										// Weight
										$package_temp['weight'] += $item['weight'];
										// Quantity
										$package_temp['quantity'] += 1;
										
										// Log
										$this->log("Item ".$item_k." fits in box ".$box_k." (volume: ".$package_temp['volume'].", weight: ".$package_temp['weight'].", quantity: ".$package_temp['quantity'].")");
										
										// Filled box? // Since we're 'padding' things when we test, it'll probably never fit exactly unless there's only 1 item in the box...should find a way to accurately test this though
										if($package_temp['volume'] >= $box['volume'] or $package_temp['weight'] >= $box['weight_max']) {
											if($x < $quantity) $package_temp_items_unpacked += 1; // Still have quantity remaining so at least 1 unpacked
											$x = $quantity + 1;
										}
									}
									// Didn't fit
									else {
										$package_temp_items_unpacked += 1;
									}
								}
										
								// Filled box? Can only test if we know we have unpacked items, if not we have to keep looking to see if we do or not // Since we're 'padding' things when we test, it'll probably never fit exactly unless there's only 1 item in the box...should find a way to accurately test this though
								if($package_temp_items_unpacked and ($package_temp['volume'] >= $box['volume'] or $package_temp['weight'] >= $box['weight_max'])) {
									break;
								}
							}
						}
						
						// Fit all items in this box, we can stop looking
						if(!$package_temp_items_unpacked and $package_temp['items']) {
							// Log
							$this->log("All items fit in box ".$box['x']);
							
							// Store
							$package = $package_temp;
							
							// End loop
							break;
						}
						// Didn't fit all, store how much it did so we can get the box which fit the most volume later
						else if(isset($package_temp['items'])) {
							// Log
							$this->log("We couldn't fit ".$package_temp_items_unpacked." item".($package_temp_items_unpacked == 1 ? "" : "s")." in box ".$box['x'].".");
							
							// Store
							$packages_temp[] = $package_temp;
						}
					}
					// Didn't all fit in any boxes, get package with most total item volume (in least box volume...should add that to sorting too)
					if(!$package and $packages_temp) {
						// Get
						usort($packages_temp,array('\Angel\Packer\Packer','rsort'));
						$package = $packages_temp[0];
						
						// Log
						$this->log("Didn't all fit in any boxes, getting package with most total item volume.");
					}
					
					// Fit items in a box
					if($package) {
						// Update packed item quantities
						foreach($package['items'] as $item_k => $item) {
							if(!isset($this->items[$item_k]['quantity_packed'])) $this->items[$item_k]['quantity_packed']  = 0;
							$this->items[$item_k]['quantity_packed'] += $item['quantity'];
						}
							
						// Store
						$packages[] = $package;
						
						// Log
						$this->log("Packed ".$package['quantity']." items in box ".$package['box']['x'].".");
					}
					// Couldn't fit items in any boxes
					else {
						$packages = NULL;
						$this->error("Couldn't fit all items into available boxes.");
						break;
					}
				
					// Limit
					$debug_counter += 1;
					if($debug_counter >= $debug_limit) {
						$packages = NULL;
						$this->log("Reached loop limit of ".$debug_limit.". There's probably an error in the code somewhere");
						break;
					}
				}
				// Already fit all items
				else {
					$this->log("All items packed.");
					break;
				}
			}
			$this->log(" ","html");
			
			// Log
			$this->log("Packages: ".$this->return_array($packages));
			
			// Return
			return $packages;
		}
		
		/**
		 * Determines if given 'item' fits in given 'box' along with other items already in the box 'package'.
		 *
		 * @param array $package The box's current package info, including the current 'items' in it.
		 * @param array $item The item's inforamtion, including dimensions and weight.
		 * @return boolean Whether or not it fits.
		 */
		function fits($package,$item) {
			// Weight
			$package['weight'] += $item['weight'];
			if($package['box']['weight_max'] and $package['weight'] > $package['box']['weight_max']) {
				$this->log("The package weight with item ".$item['x']." added (".$package['weight'].") exceeds the box ".$package['box']['x']." weight limit (".$package['box']['weight_max'].").");
				return false;
			}
			
			// Larges first
			arsort($item['dimensions']);
			arsort($package['box']['dimensions']);
			
			// Volume - if item's combined volume is greater than box volume, it obviously won't fit
			if(!isset($package['volume'])) $package['volume'] = 0;
			$package['volume'] += $item['volume'];
			if($package['volume'] > $package['box']['volume']) {
				$this->log("The package volume with item ".$item['x']." added (".$package['volume'].") exceeds the box ".$package['box']['x']." volume (".$package['box']['volume'].").");
				return false;
			}
			// Volume (padded) - if item's combined volume with padding added is greater than box volume, probabyl won't fit
			if($this->c['volume_pad']) {
				$package['volume'] = ($package['volume'] * $this->c['volume_pad']);
				if($package['volume'] > $package['box']['volume']) {
					$this->log("The package volume with item ".$item['x']." added and padding applied (".$package['volume'].") exceeds the box ".$package['box']['x']." volume (".$package['box']['volume'].").");
					return false;
				}
			}
			
			// Dimensions - fit it in the box overall
			if($this->c['dimensions_check']) {
				$dimensions = NULL;
				foreach($item['dimensions'] as $item_x => $item_dimension) {
					// Check all box dimensions to see if it fits
					foreach($package['box']['dimensions'] as $box_x => $box_dimension) {
						if(isset($dimensions['box'][$box_x])) continue;
						if($box_dimension >= $item_dimension) {
							$dimensions['item'][$item_x] = $item_dimension;
							$dimensions['box'][$box_x] = $box_dimension;
							//$this->log("Item's ".$item_dimension." side fits within boxes ".$box_dimension." side");
							break;			
						}
					}
					// Doesn't fit
					if(!isset($dimensions['item'][$item_x])) {
						$this->log("Item ".$item['x']." (".$item['dimensions'][0]." x ".$item['dimensions'][1]." x ".$item['dimensions'][2].") exceeds the box ".$package['box']['x']." dimensions (".$package['box']['dimensions'][0]." x ".$package['box']['dimensions'][1]." x ".$package['box']['dimensions'][2].").");
						return false;
					}
				}
			}
			
			// Pack - make sure it fits in the box with other items
			# build this
			
			// Default
			return true;
		}
		
		/**
		 * Sorts array of boxes/items in ascending order (smallest to largest) by volume.
		 *
		 * Used with PHP's usort() to sort the associative arrays of boxes/items.
		 *
		 * @param array $a The first value we want to compare.
		 * @param array $b The second value we want to compare.
		 * @return int Whether or not value $a is greater than (1), less than (-1), or equal to (0) value $b
		 */
		function sort($a,$b) {
			if($a['volume'] == $b['volume']) return 0;
			return ($a['volume'] > $b['volume'] ? 1 : -1);
		}
		
		/**
		 * Sorts array of boxes/items in descending order (largest to smallest) by volume.
		 *
		 * Used with PHP's usort() to sort the associative arrays of boxes/items.
		 *
		 * @param array $a The first value we want to compare.
		 * @param array $b The second value we want to compare.
		 * @return int Whether or not value $a is less than (1), greater than (-1), or equal to (0) value $b
		 */
		function rsort($a,$b) {
			if($a['volume'] == $b['volume']) {
				// Volume and box volume - note, when comparing
				if($a['box']['volume']) {
					if($a['box']['volume'] == $b['box']['volume']) return 0;
					return ($a['box']['volume'] > $b['box']['volume'] ? 1 : -1);
				}
				// Volume only
				if($a['volume'] == $b['volume']) return 0;
			}
			return ($a['volume'] < $b['volume'] ? 1 : -1);
		}
		
		/**
		 * Either returns an array of or (if $string passed) saves log information, usually a message or error.
		 *
		 * @param string $string The string we want to log. Default = NULL.
		 * @param string $type The type of string we're logging: message, error, html. Default = message
		 */
		function log($string = NULL,$type = "message") {
			// Log
			if($string) {
				// Store
				$this->log[] = array(
					'type' => $type,
					'string' => $string,
					'time' => time(),
				);
				
				// Debug
				if($this->c['debug']) print "
".($type != "html" ? $type.": " : "").$string."<br />";
			}
			
			// Return
			return $this->log;
		}
		
		/**
		 * Either returns the most recent 'new' error or, if $error passed, stores the given error in the log.
		 *
		 * New is defined as one we haven't yet returned yet.
		 *
		 * @param return array An array of new errors.
		 */
		function error($error = NULL) {
			// Save
			if($error) {
				// Log
				$this->log($error,'error');
				
				// Return
				return $error;
			}
			
			// Get newest error
			if($this->log) {
				foreach($this->log as $x => $v) {
					if($v['type'] == "error" and !$v['returned']) {
						$this->log[$x]['returned'] = 1;
						return $v['string'];
					}
				}
			}
		}
		
		/**
		 * Returns an array of any new errors.
		 *
		 * New is defined as one we haven't yet returned yet.
		 *
		 * @param return array An array of new errors.
		 */
		function errors() {
			// Find new errors
			if($this->log) {
				foreach($this->log as $x => $v) {
					if($v['type'] == "error" and !$v['returned']) {
						$array[] = $v['string'];
						$this->log[$x]['returned'] = 1;
					}
				}	
			}
			
			// Return
			return $array;
		}
		
		/**
		 * Returns an array as a string in a more easily readable format.
		 * 
		 * @param array $array The array we want to print.
		 * @return string A string representation of the array.
		 */
		function return_array($array) {
			ob_start();
			
			print "<xmp>";
			print_r($array);
			print "</xmp>";
			
			$contents = ob_get_contents();
			ob_end_clean();
			return $contents;
		}
	}
}
?>