<?php
	// Copyright 2016 Marcel Haupt
	// http://marcel-haupt.eu/
	//
	// Licensed under the Apache License, Version 2.0 (the "License");
	// you may not use this file except in compliance with the License.
	// You may obtain a copy of the License at
	//
	// http ://www.apache.org/licenses/LICENSE-2.0
	//
	// Unless required by applicable law or agreed to in writing, software
	// distributed under the License is distributed on an "AS IS" BASIS,
	// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	// See the License for the specific language governing permissions and
	// limitations under the License.
	//
	// Github Project: https://github.com/cbacon93/DCSServerStats

	
class SimStats {
	private $mysqli;
	
	public function SimStats(mysqli $mysqli) {
		$this->mysqli = $mysqli;
	}
	
	public function echoSiteContent() 
	{
		if (isset($_GET['pid'])) 
		{
			$this->echoPilotStatistic($_GET['pid']);
		} 
		else if (isset($_GET['flights'])) 
		{
			echo "<h2>Flights</h2><br><br>";
			$this->echoFlightsTable();
		} 
		else if (isset($_GET['aircrafts'])) 
		{
			echo "<h2>Aircrafts</h2><br><br>";
			$this->echoAircraftsTable();
		} 
		else if (isset($_GET['weapons'])) 
		{
			echo "<h2>Weapons</h2><br><br>";
			$this->echoWeaponsTable();
		} 
		else if (isset($_GET['map'])) {
			echo "<h2>Live Radar Map</h2><br><br>";
			$this->echoMapScript();
		} 
		else
		{
			echo "<h2>Pilots</h2><br><br>";
			$this->echoPilotsTable();
		}
	}
		
	public static function timeToString($time) {
		$flight_hours = floor($time / 60 / 60);
		$flight_mins = floor($time / 60) - $flight_hours * 60;
		$flight_secs = $time  - $flight_mins * 60 - $flight_hours * 3600;
		
		if ($flight_mins < 10)
			$flight_mins = '0' . $flight_mins;
		if ($flight_secs < 10)
			$flight_secs = '0' . $flight_secs;
			
		return "$flight_hours:$flight_mins:$flight_secs";
	}
	
	
	function echoFooter() {
		$result = $this->mysqli->query("SELECT * FROM dcs_parser_log ORDER BY id DESC LIMIT 1");
		if ($row = $result->fetch_object()) {
		
			echo "Last update at " . date('G:i', $row->time) . " processed " . $row->events . " events in " . $row->durationms .  " ms";
		}
	}
	
	
	
	public function getPilotsTable() {
		$pilots = array();
		
		$result = $this->mysqli->query("SELECT * FROM pilots WHERE name<>'AI' ORDER BY flighttime DESC");
		while($row = $result->fetch_object()) {
			$pilots[] = $row;
		}
		
		return $pilots;
	}
	
	
	public function getWeaponsTable() {
		$weapons = array();
		
		$result = $this->mysqli->query("SELECT * FROM weapons ORDER BY hits DESC");
		while($row = $result->fetch_object()) {
			$weapons[] = $row;
		}
		
		return $weapons;
	}
	
	
	
	public function getPilotsFlightsTable($pilotid = -1) {
		$flights = array();
		
		if ($pilotid > 0) {
			$prep = $this->mysqli->prepare("SELECT 0, '', aircrafts.name, flights.coalition, flights.takeofftime, flights.landingtime, flights.duration, flights.endofflighttype FROM flights, aircrafts WHERE flights.pilotid=? AND aircrafts.id=flights.aircraftid ORDER BY flights.takeofftime DESC LIMIT 10");
			$prep->bind_param('i', $pilotid);
		} else {
			$prep = $this->mysqli->prepare("SELECT pilots.id AS pid, pilots.name AS pname, aircrafts.name, flights.coalition, flights.takeofftime, flights.landingtime, flights.duration, flights.endofflighttype FROM flights, aircrafts, pilots WHERE pilots.id=flights.pilotid AND aircrafts.id=flights.aircraftid AND pilots.name<>'AI' ORDER BY flights.takeofftime DESC LIMIT 30");
		}
		$prep->execute();
		
		$row = new stdClass();
		$prep->bind_result($row_pilotid, $row_pilotname, $row_acname, $row_coalition, $row_takeofftime, $row_landingtime, $row_duration, $row_endofflighttype);
		
		while($prep->fetch()) {
			$flight = new stdClass();
			$flight->pilotid = $row_pilotid;
			$flight->pilotname = $row_pilotname;
			$flight->acname = $row_acname;
			$flight->coalition = $row_coalition;
			$flight->takeofftime = $row_takeofftime;
			$flight->landingtime = $row_landingtime;
			$flight->duration = $row_duration;
			$flight->endofflighttype = $row_endofflighttype;
			$flights[] = $flight;
		}
		$prep->close();
		
		return $flights;
	}
	
	
	public function getPilotsAircraftTable($pilotid = -1) {
		$aircrafts = array();
		
		if ($pilotid > 0) {
			$prep = $this->mysqli->prepare("SELECT pilot_aircrafts.flights, aircrafts.name, pilot_aircrafts.time, pilot_aircrafts.ejects, pilot_aircrafts.crashes, pilot_aircrafts.kills, pilots.show_kills FROM pilot_aircrafts, aircrafts, pilots WHERE pilot_aircrafts.pilotid=? AND pilots.id = pilot_aircrafts.pilotid AND pilot_aircrafts.aircraftid=aircrafts.id ORDER BY pilot_aircrafts.time DESC");
			$prep->bind_param('i', $pilotid);
		} else {
			$prep = $this->mysqli->prepare("SELECT aircrafts.flights, aircrafts.name, aircrafts.flighttime, aircrafts.ejects, aircrafts.crashes, aircrafts.kills, 1 FROM aircrafts ORDER BY aircrafts.flighttime DESC");
		}
		$prep->execute();
		
		$row = new stdClass();
		$prep->bind_result($row_flights, $row_acname, $row_time, $row_ejects, $row_crashes, $row_kills, $row_show_kills);
		
		while($prep->fetch()) {
			$aircraft = new stdClass();
			$aircraft->flights = $row_flights;
			$aircraft->acname = $row_acname;
			$aircraft->time = $row_time;
			$aircraft->ejects = $row_ejects;
			$aircraft->crashes = $row_crashes;
			$aircraft->kills = $row_kills;
			$aircraft->show_kills = $row_show_kills;
			
			$aircrafts[] = $aircraft;
		}
		$prep->close();
		
		return $aircrafts;
	}
	
	
	public function getActiveFlight($pilotid) {
		$prep = $this->mysqli->prepare("SELECT dcs_events.InitiatorCoa, dcs_events.InitiatorType, dcs_events.time FROM pilots, dcs_events WHERE pilots.id=? AND dcs_events.event IN ('S_EVENT_TAKEOFF', 'S_EVENT_BIRTH_AIRBORNE') AND dcs_events.InitiatorPlayer=pilots.name ORDER BY dcs_events.id DESC LIMIT 1");
		$prep->bind_param('i', $pilotid);
		$prep->execute();
	
		$row = new stdClass();
		$prep->bind_result($row->coalition, $row->actype, $row->takeofftime);
		
		//if active flight exists - return, otherwise fail
		if ($prep->fetch()) {
			$prep->close();
			return $row;
		}
		$prep->close();
		return false;
	}
	
	
	public function getFlightPath($pilotid, $aircraftid, $search_time, $raw_id) {
		$path = array();
		
		//get flight path line
		$prep = $this->mysqli->prepare("SELECT pd.missiontime, pd.lat, pd.lon FROM position_data AS pd WHERE pd.raw_id=? AND pd.time<=? AND pd.pilotid=? AND pd.aircraftid=? ORDER BY pd.time DESC"); //AND 
		$prep->bind_param('iiii', $raw_id, $search_time, $pilotid, $aircraftid);
		$prep->execute();
		
		$row = new stdClass();
		$prep->bind_result($row_missiontime, $row_lat, $row_lon);
		
		$last_misst = 99999999;
		while($prep->fetch()) {
			//flight ended definately
			if ($last_misst < $row_missiontime) break;
			
			$point = new stdClass();
			$point->missiontime = $row_missiontime;
			$point->lat = $row_lat;
			$point->lon = $row_lon;
			$path[] = $point;
			
			$last_misst = $row_missiontime;
		}
		
		$prep->close();
		return $path;
	}
	
	
	public function getCurrentFlightPositions() {
		$flights = array();
		
		$result = $this->mysqli->query("SELECT pd.id, pd.lat, pd.lon, pilots.name as pname, aircrafts.name as acname, pd.raw_id, pd.pilotid, pd.aircraftid, pd.missiontime, pd.time FROM position_data AS pd, pilots, aircrafts WHERE pd.time>" . (time()-120) . " AND pd.pilotid=pilots.id AND aircrafts.id=pd.aircraftid AND pd.id IN (SELECT MAX(pd2.id) FROM position_data AS pd2 GROUP BY pd2.raw_id)");
		
		while($row = $result->fetch_object()) {
			$flights[] = $row;
		}
		
		return $flights;
	}
		
		
		
	public function echoPilotsTable() {
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Pilot</th><th>Flights</th><th>Flight time</th><th>Kills</th><th>Ejections</th><th>Crashes</th><th>last active</th><th>status</th></tr>";
		
		$pilots = $this->getPilotsTable();
		
		foreach($pilots as $aid=>$pilot) {
			$onlinestatus = "<p class='pilot_offline'>On the Ground</p>";
			if ($pilot->online == 1)
				$onlinestatus = "<p class='pilot_online'>Flying</p>";
			
			if (!$pilot->show_kills) {
				$pilot->kills = '-';
				$pilot->ejects = '-';
				$pilot->crashes = '-';
			}
			
			echo "<tr onclick=\"window.document.location='?pid=" . $pilot->id . "'\" class='table_row_" . $aid%2 . "'><td>" . $pilot->name . "</td><td>" . $pilot->flights . "</td><td>" . $this->timeToString($pilot->flighttime) . "</td><td>" . $pilot->kills . "</td><td>" . $pilot->ejects . "</td><td>" . $pilot->crashes . "</td><td>" . date('d.m.Y', $pilot->lastactive) . "</td><td>" . $onlinestatus . "</td></tr>";
			
			
		}
		
		if (sizeof($pilots) == 0) {
			echo "<tr><td style='text-align: center' colspan='8'>No Pilots listed</td></tr>";
		}
		
		echo "</table>";
	}
	
	
	public function getPilotsStatistic($pilotid) {
		//get pilot information
		$prep = $this->mysqli->prepare("SELECT id, name, flighttime, flights, lastactive, online FROM pilots WHERE id=?");
		$prep->bind_param('i', $pilotid);
		$prep->execute();
		
		$row = new stdClass();
		$prep->bind_result($row->id, $row->name, $row->flighttime, $row->flights, $row->lastactive, $row->online);
		if ($prep->fetch()) {
			$prep->close();
			return $row;
		}
		$prep->close();
		return false;
	}
	
	
	
	public function echoPilotsFlightsTable($pilotid) {	
		$flights = $this->getPilotsFlightsTable($pilotid);
		
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Aircraft</th><th>Coalition</th><th>Takeoff</th><th>Landing</th><th>Duration</th><th>Type of Landing</th></tr>";
		
		foreach($flights as $aid=>$flight) {
			echo "<tr class='table_row_" . $aid%2 . "'><td>" . $flight->acname . "</td><td>" . $flight->coalition . "</td><td>" . date('d.m.Y - H:i', $flight->takeofftime) . "</td><td>" . date('d.m.Y - H:i', $flight->landingtime) . "</td><td>" . $this->timeToString($flight->duration) . "</td><td>" . $flight->endofflighttype . "</td></tr>";
		}
		
		if (sizeof($flights) == 0) {
			echo "<tr><td style='text-align: center' colspan='6'>No Flights listed</td></tr>";
		}
		
		echo "</table><br><br>";
	}
	
	
	public function echoFlightsTable() {	
		$flights = $this->getPilotsFlightsTable();
		
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Pilot</th><th>Aircraft</th><th>Coalition</th><th>Takeoff</th><th>Landing</th><th>Duration</th><th>Type of Landing</th></tr>";
		
		foreach($flights as $aid=>$flight) {
			echo "<tr onclick=\"window.document.location='?pid=" . $flight->pilotid . "'\" class='table_row_" . $aid%2 . "'><td>" . $flight->pilotname . "</td><td>" . $flight->acname . "</td><td>" . $flight->coalition . "</td><td>" . date('d.m.Y - H:i', $flight->takeofftime) . "</td><td>" . date('d.m.Y - H:i', $flight->landingtime) . "</td><td>" . $this->timeToString($flight->duration) . "</td><td>" . $flight->endofflighttype . "</td></tr>";
		}
		
		if (sizeof($flights) == 0) {
			echo "<tr><td style='text-align: center' colspan='7'>No Flights listed</td></tr>";
		}
		
		echo "</table><br><br>";
	}
	
	
	public function echoPilotsAircraftsTable($pilotid) {
		$flights = $this->getPilotsAircraftTable($pilotid);
		
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Aircraft</th><th>Flights</th><th>Flight time</th><th>Kills</th><th>Ejections</th><th>Crashes</th></tr>";
		
		foreach($flights as $aid=>$flight) {
			if (!$flight->show_kills) {
				$flight->crashes = '-';
				$flight->ejects = '-';
				$flight->kills = '-';
			}
			
			echo "<tr class='table_row_" . $aid%2 . "'><td>" . $flight->acname . "</td><td>" . $flight->flights . "</td><td>" . $this->timeToString($flight->time) . "</td><td>" . $flight->kills . "</td><td>" . $flight->ejects . "</td><td>" . $flight->crashes . "</td></tr>";
		}
		
		if (sizeof($flights) == 0) {
			echo "<tr><td style='text-align: center' colspan='6'>No Aircrafts listed</td></tr>";
		}
		
		
		echo "</table><br><br>";
	}
	
	
	public function echoAircraftsTable() {
		$flights = $this->getPilotsAircraftTable();
		
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Aircraft</th><th>Flights</th><th>Flight time</th><th>Kills</th><th>Ejections</th><th>Crashes</th></tr>";
		
		foreach($flights as $aid=>$flight) {
			echo "<tr class='table_row_" . $aid%2 . "'><td>" . $flight->acname . "</td><td>" . $flight->flights . "</td><td>" . $this->timeToString($flight->time) . "</td><td>" . $flight->kills . "</td><td>" . $flight->ejects . "</td><td>" . $flight->crashes . "</td></tr>";
		}
		
		if (sizeof($flights) == 0) {
			echo "<tr><td style='text-align: center' colspan='6'>No Aircrafts listed</td></tr>";
		}
		
		echo "</table><br><br>";
	}
	
	
	private function echoActiveFlight($pilotid) {
				
		if ($flight = $this->getActiveFlight($pilotid)) {
			$duration = time() - $flight->takeofftime;
			echo "<b>Active Flight:</b> <br>";
			echo "<table class='table_stats'><tr class='table_header'><th>Aircraft</th><th>Coalition</th><th>Takeoff</th><th>Duration</th></tr>";
			echo "<tr><td>" . $flight->actype . "</td><td>" . $flight->coalition . "</td>";
			echo "<td>" . date('H:i d.m.Y', $flight->takeofftime) . "</td>";
			echo "<td><p class='js_timer'>" . $this->timeToString($duration) . "</p></td></tr></table>";
				
			echo "<br><br>";
		}
		
	}
	
	
	public function echoPilotStatistic($pilotid) {
		
		if ($pilot = $this->getPilotsStatistic($pilotid)) {
			
			$pilotid = $pilot->id;
			$online = $pilot->online;
			$onlinestatus = "<p class='pilot_offline'>On the Ground</p>";
			if ($pilot->online == 1)
				$onlinestatus = "<p class='pilot_online'>Flying</p>";
			
			echo "<h2>Pilot " . $pilot->name . "</h2><br><br>";
			echo "<table class='table_stats'><tr class='table_row_0'><td>Total Flight Time: </td><td>" . $this->timeToString($pilot->flighttime) . "</td></tr>";
			echo "<tr class='table_row_1'><td>Flights: </td><td>" . $pilot->flights . "</td></tr>";
			echo "<tr class='table_row_0'><td>Last Activity: </td><td>" . date('d.m.Y', $pilot->lastactive) . "</td></tr>";
			echo "<tr class='table_row_1'><td>Status: </td><td>" . $onlinestatus . "</td></tr></table>";
			echo "<br><br>";
			
			
			//try to print active flight
			if ($online == 1) {
				$this->echoActiveFlight($pilotid);
			}
			
			echo "<b>Last Flights:</b>";
			$this->echoPilotsFlightsTable($pilotid);
			
			echo "<b>Flown Airplanes</b>";
			$this->echoPilotsAircraftsTable($pilotid);
					
		} else {
			echo "Pilot not found!";
		}
	}
	
	
	public function echoWeaponsTable() {
		$weapons = $this->getWeaponsTable();
		
		echo "<table class='table_stats'>";
		echo "<tr class='table_header'><th>Weapon</th><th>Category</th><th>Shots</th><th>Hits</th><th>Kills</th></tr>";
		
		foreach($weapons as $weapon) {
			echo "<tr class='table_row_" . $aid%2 . "'><td>" . $weapon->name . "</td><td>" . $weapon->type . "</td><td>" . $weapon->shots . "</td><td>" . $weapon->hits . "</td><td>" . $weapon->kills . "</td></tr>";
		}
		
		if (sizeof($weapons) == 0) {
			echo "<tr><td style='text-align: center' colspan='5'>No Weapons listed</td></tr>";
		}
		
		echo "</table><br><em>Gunshots are not counted.</em><br>";
	}
	
	
	public function echoMapScript() {
		echo "<div id=\"map\"></div>";
		echo "<script src=\"https://maps.googleapis.com/maps/api/js?key=AIzaSyBZoFosVL27IeHx57Wujg-v_aW3slJWItA&callback=initMap\"
	        async defer></script>";
	}
	
	public function getMapInfoJSON() {
		$flights = $this->getCurrentFlightPositions();
		
		$json = "{\"flights\": [\n";
		
		foreach($flights as $id=>$flight) {
			
			//comma separator
			if ($id != 0) {$json .=  ",";}
			
			//pilot information
			$json .= "{\"pilot\": \"" . $flight->pname . "\",\"ac\": \"" . $flight->acname . "\",\"lat\": " . $flight->lat . ",\"lng\": " . $flight->lon . ",\"path\": [";
			
			
			//$text = "<table><tr><td>Pilot:</td><td>" . $flight->pname . "</td></tr><tr><td>Aircraft:</td><td>" . $flight->acname . "</td></tr></table>";
			
			//get flight path
			$fpath = $this->getFlightPath($flight->pilotid, $flight->aircraftid, $flight->time, $flight->raw_id);
			foreach($fpath as $aid=>$pt) {
				//comma separator
				if ($aid != 0) {$json .=  ",";}
				
				$json .= "{\"lat\": " . $pt->lat . ",\"lng\": " . $pt->lon . "}";
			}
			$json .= "]}\n";
	
		}
		
		$json .= "]}";
		
		return $json;
	}
}
 	
?>