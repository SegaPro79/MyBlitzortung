diff --git a/includes/functions_dynmap.inc.php b/includes/functions_dynmap.inc.php
index ba5ec7c9a4623a6a3cb707837bd03d570149cc27..3a17127978848919815acf7282dbca402f55493f 100644
--- a/includes/functions_dynmap.inc.php
+++ b/includes/functions_dynmap.inc.php
@@ -1,48 +1,75 @@
 <?php
 
                echo "<div id=\"bo_gmap\"></div>";
                echo "L.tileLayer('".$tileUrl."',{tileSize:".BO_TILE_SIZE.",crossOrigin:false}).addTo(bo_map);";
 {
 	global $_BO;
 
 	$radius = $_BO['radius'] * 1000;
 
 	$info = bo_station_info();
 	
 	if ($info)
 		$station_text = $info['city'];
 		
 	$station_lat = BO_LAT;
 	$station_lon = BO_LON;
 	$center_lat = BO_MAP_LAT ? BO_MAP_LAT : BO_LAT;
-	$center_lon = BO_MAP_LON ? BO_MAP_LON : BO_LON;
-	
+        $center_lon = BO_MAP_LON ? BO_MAP_LON : BO_LON;
+
+        if (BO_MAP_PROVIDER === 'leaflet') {
+                $interval = isset($_BO['mapcfg'][0]['upd_intv']) ? intval($_BO['mapcfg'][0]['upd_intv']) : 1;
+                $multi = intval(BO_TILE_UPDATE_MULTI);
+                $sub   = intval(BO_TILE_UPDATE_SUB);
+                if ($sub > $interval) $sub = 0;
+                $now  = time() - $sub * 60;
+                $arg  = gmdate('j_H', $now);
+                $arg .= '_' . ($interval > 0 ? floor(gmdate('i', $now) / $interval * $multi) : 0);
+                if (bo_user_get_level())
+                        $arg .= '_1';
+
+                $tileUrl = bo_tile_url()."?tile&type=0".bo_lang_arg('tile')."&bo_t=".$arg."&zoom={z}&x={x}&y={y}";
+
+                echo "<div id=\"bo_gmap\" style=\"width:500px; height:400px;\"></div>";
+                echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
+                echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
+                echo '<script>';
+                echo "var bo_map = L.map('bo_gmap').setView([$lat, $lon], $zoom);";
+                echo "L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(bo_map);";
+                echo "L.tileLayer('".$tileUrl."',{tileSize:".BO_TILE_SIZE."}).addTo(bo_map);";
+                if ($show_station & 1) {
+                        echo \"L.marker([$station_lat,$station_lon]).addTo(bo_map).bindPopup(\\\""._BC($station_text)."\\\");\";
+                }
+                echo '</script>';
+                return;
+        }
+
 ?>
 
 
-	<script type="text/javascript" id="bo_script_map">
+        <script type="text/javascript" id="bo_script_map">
 	
 	
 <?php if ($poverlay) { ?>
 	//bo_ProjectedOverlay
 	//Source: http://www.usnaviguide.com/v3maps/js/bo_ProjectedOverlay.js
 	var bo_ProjectedOverlay = function(map, imageUrl, bounds, opts)
 	{
 	 google.maps.OverlayView.call(this);
 	 this.url_ = imageUrl ;
 	 this.bounds_ = bounds ;
 	 this.addZ_ = opts.addZoom || '' ;				// Add the zoom to the image as a parameter
 	 this.id_ = opts.id || this.url_ ;				// Added to allow for multiple images
 	 this.percentOpacity_ = opts.opacity || 50 ;
 	 this.layer_ = opts.layer || 0;
 	 this.map_ = map;
 	}
 <?php } ?>
 	
 	var bo_map;
 	var bo_home;
 	var bo_home_zoom;
 	var bo_infobox;
 	var bo_loggedin = <?php echo intval(bo_user_get_level()) ?>;
 	var boDistCircle;
 	var bo_place_markers = [];
