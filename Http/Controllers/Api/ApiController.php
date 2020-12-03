<?php

namespace Modules\SmartAcars\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Enums\AcarsType;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Enums\UserState;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepComment;
use App\Models\User;
use App\Repositories\AircraftRepository;
use Illuminate\Http\Request;
use Modules\SmartAcars\Models\Sesion;

/**
 * class ApiController
 * @package Modules\SmartAcars\Http\Controllers\Api
 */
class ApiController extends Controller
{
    private $aircraftRepo;

    public function __construct(
        AircraftRepository $aircraftRepo
    )
    {
        $this->aircraftRepo = $aircraftRepo;
    }

    /**
     * Just send out a message
     * @param Request $request
     * @return mixed
     */
    public function frame(Request $request)
    {
        logger($request->get('action'));
        logger($request->all());
        switch($request->get('action')){
            case 'manuallogin':
                $response = $this->manualLogin($request);
                break;
            case 'automaticlogin':
                $response = $this->automaticLogin($request);
                break;
            case 'verifysession':
                $response = $this->verifySesion($request);
                break;
            case 'getaircraft':
                $response = $this->getAircraft($request);
                break;
            case 'getairports':
                $response = $this->getAirports($request);
                break;
            case 'getpilotcenterdata':
                $response = $this->getPilotCenterData($request);
                break;
            case 'getbidflights':
                $response = $this->getBidFlights($request);
                break;
            case 'searchpireps':
                $response = $this->searchPireps($request);
                break;
            case 'createflight':
                $response = $this->createFlight($request);
                break;
            case 'getpirepdata':
                $response = $this->getPirepData($request);
                break;
            case 'positionreport':
                $response = $this->positionReport($request);
                break;
            case 'filepirep':
                $response = $this->filePirep($request);
                break;
            default:
                $response ="Script OK, Frame Version: phpvms7-thor, Interface Version: phpvms7-thor";
                logger($request->all());
                break;
        }
        logger($response);
        if(is_array($response)){
            echo implode(";",$response);
        }else{
            echo $response;
        }
        exit;
        //return $this->message('Hello, world!');
    }

    private function manualLogin(Request $request){
        $pilot = User::where('pilot_id',$request->get('userid'))->where('api_key',$request->get('password'))->first();
        if(is_null($pilot)){
            return "AUTH_FAILED";
        }
        return $this->sesionCreate($pilot,$request->get('sessionid'));
        //echo($res['dbid'] . "," . $res['code'] . "," . $res['pilotid'] . "," . $_GET['sessionid'] . "," . $res['firstname'] . "," . $res['lastname'] . "," . $res['email'] . "," . $res['ranklevel'] . "," . $res['rankstring']);
    }

    /**
     * @param Request $request
    'action' => 'automaticlogin',
    'new' => 'true',
    'dbid' => '1',
    'sessionid' => 'yj2AI0tNiFDo5sdqAKAuebbT9Mhz3DGn9psuALTtnUxWgHbjm8r9eZtliCKLldHO',
    'oldsessionid' => 'saBEiDI2dLD4zA3dS7VqdLrRVF8CoFLm5JLNpHwLz0pPFJhNPRVkLsNstYpZwnln',
     *
     */
    protected function automaticLogin(Request $request){
        $session = $this->sessionVerify($request->get('dbid'),$request->get('oldsessionid'));
        if(is_null($session)){
            return "AUTH_FAILED";
        }
        $session->status = "USED";
        $session->save();
        return $this->sesionCreate($session->user,$request->get('sessionid'));
    }

    protected function verifySesion(Request $request){
        $session = $this->sessionVerify($request->get('dbid'),$request->get('sessionid'));
        if(is_null($session)){
            return "AUTH_FAILED";
        }
        return $session->session . "," . $session->user->name . ",";
    }

    private function sesionCreate(User $pilot,string $sesionid){
        $session = Sesion::where('session',$sesionid)->first();
        if(!is_null($session)){
            return "AUTH_FAILED_SESSION";
        }

        $session = new Sesion([
            'user_id' => $pilot->id,
            'session' => $sesionid,
        ]);
        $session->save();
        switch($pilot->state){
            case UserState::ACTIVE:
                //			echo($res['dbid'] . "," . $res['code'] . "," . $res['pilotid'] . "," . $_GET['sessionid'] . "," . $res['firstname'] . "," . $res['lastname'] . "," . $res['email'] . "," . $res['ranklevel'] . "," . $res['rankstring']);
                return($pilot->id. "," . $pilot->ident . "," . $pilot->id . "," . $session->session . "," . $pilot->name . "," . "," . $pilot->email . "," . $pilot->rank_id . "," . $pilot->rank->name);
                break;
            case UserState::PENDING:
                return "ACCOUNT_UNCONFIRMED";
                break;
            case UserState::REJECTED:
                return("ACCOUNT_INACTIVE");
                break;
            case UserState::ON_LEAVE:
                return("ACCOUNT_INACTIVE");
                break;
            case UserState::SUSPENDED:
                return("ACCOUNT_INACTIVE");
                break;
            default:
                return "AUTH_FAILED";
                break;
        }
    }

    private function sessionVerify($user_id,$session){
        $session = Sesion::where('user_id',$user_id)->where('session',$session)->where('status','')->first();
        if(is_null($session) || $session->created_at < (new \DateTime())->sub(new \DateInterval('PT24H'))){
            return null;
        }
        return $session;
    }

    private function getAircraft(Request $request){
        $aircrafts = Aircraft::where('status',AircraftStatus::ACTIVE)->where('state',AircraftState::PARKED)->get();
        $return = [];
        foreach ($aircrafts as $aircraft){
            $return[] = $aircraft->id . "," . $aircraft->name . "," . $aircraft->airport_id . "," . $aircraft->registration . "," . 9999 . "," . 9999 . "," . 0;
        }
        return $return;
    }

    private function getAirports(Request $request){
        $airports = Airport::all();
        $return = [];
        foreach ($airports as $airport){
            //echo ($airport->id . "," . $airport->full_name . "," . $airport->icao . "," . $ac[$res['format']['registration']] . "," . $ac[$res['format']['maxpassengers']] . "," . $ac[$res['format']['maxcargo']] . "," . $ac[$res['format']['requiredranklevel']]);
            $return[] = $airport->id . "|" . strtoupper($airport->icao) . "|" . $airport->full_name . "|" . $airport->lat . "|" . $airport->lon . "|" . $airport->country;
        }
        return $return;
    }

    private function getPilotCenterData(Request $request){
        $pilot = User::where('id',$request->get('dbid'))->first();
        if(is_null($pilot)){
           return null;
        }
        //echo($res['totalhours'] . "," . $res['totalflights'] . "," . $res['averagelandingrate'] . "," . $res['totalpireps']);
        $ftHours = round($pilot->flight_time/60,0,PHP_ROUND_HALF_DOWN);
        $ftMinutes = ($pilot->flight_time%60);
        return ($ftHours.".".$ftMinutes . "," . $pilot->flights . "," . 0 . "," . count($pilot->pireps));
    }

    /**
     * @param Request $request
     * @TODO No funciona
     * Debería devolver los PIREPS disponibles puesto que luego envía el routeid y si no saldría el routeid del flight
     */
    private function getBidFlights(Request $request){

        $bids = Bid::where('user_id',$request->get('dbid'))->get();
        $aircraft = Aircraft::where('status',AircraftStatus::ACTIVE)->where('state',AircraftState::PARKED)->firstOrFail();
        $return = [];
        if(count($bids)==0){
            return ("NONE");
        }
        foreach ($bids as $bid){
            //echo($schedule[$res['format']['bidid']] . "|" . $schedule[$res['format']['routeid']] . "|" . $schedule[$res['format']['code']] . "|" . $schedule[$res['format']['flightnumber']] . "|" . $schedule[$res['format']['departureicao']] . "|" . $schedule[$res['format']['arrivalicao']] . "|" . $schedule[$res['format']['route']] . "|" . $schedule[$res['format']['cruisingaltitude']] . "|" . $schedule[$res['format']['aircraft']] . "|" . $schedule[$res['format']['duration']] . "|" . $schedule[$res['format']['departuretime']] . "|" . $schedule[$res['format']['arrivaltime']] . "|" . $schedule[$res['format']['load']] . "|" . $schedule[$res['format']['type']] . "|" . $schedule[$res['format']['daysofweek']]);
            $return[] = $bid->id . "|" . $bid->flight->id . "|CH|" . $bid->flight->flight_number . "|" . $bid->flight->dpt_airport_id . "|" . $bid->flight->arr_airport_id . "|" . $bid->flight->route . "|" . $bid->flight->level . "|" . $aircraft->id . "|" . $bid->flight->flight_time . "|" . $bid->flight->dpt_time . "|" . $bid->flight->arr_time . "|randomopen|" . $bid->flight->flight_time . "|" . "0,1,2,3,4,5,6";
        }
        return $return;
    }

    /**
     * @param Request $request
     * @TODO NO FUNCIONA
     */
    private function searchPireps(Request $request){
        $pireps = Pirep::where('user_id',$request->get('dbid'))->get();
        $return = [];
        foreach ($pireps as $pirep){
            $return[] =($pirep->id . "|" . $pirep->flight_type . "|" . $pirep->flight_number . "|" . $pirep->block_on_time . "|" . $pirep->dpt_airport_id . "|" . $pirep->arr_airport_id . "|" . $pirep->aircraft_id);
        }
        //echo($pirep[$res['format']['pirepid']] . "|" . $pirep[$res['format']['code']] . "|" . $pirep[$res['format']['flightnumber']] . "|" . $pirep[$res['format']['date']] . "|" . $pirep[$res['format']['departureicao']] . "|" . $pirep[$res['format']['arrivalicao']] . "|" . $pirep[$res['format']['aircraft']]);
        return $return;
    }

    private function createFlight(Request $request){
        $session = $this->sessionVerify($request->get('dbid'),$request->get('sessionid'));
        if(is_null($session)){
            return 'AUTH_FAILED';
        }
        $flight = new Flight([
            'airline_id' => 1,
            'flight_number' => $request->get('flightnumber'),
            'dpt_airport_id' => $request->get('departureicao'),
            'arr_airport_id' => $request->get('arrivalicao'),
            'dpt_time' => ($request->get('departuretime') == "Time")?null:$request->get('departuretime'),
            'arr_time' => ($request->get('arrivaltime') == "Time")?null:$request->get('arrivaltime'),
            'level' => $request->get('cruisealtitude'),
            'flight_type' => FlightType::CHARTER_PAX_ONLY,
            'active' => 1,
            'visible' => 1,
        ]);
        $flight->save();

        $bid = new Bid([
            'user_id' => $request->get('dbid'),
            'flight_id' => $flight->id,
        ]);
        $bid->save();

        return "SUCCESS";
    }

    private function getPirepData(Request $request){

    }

    private function positionReport(Request $request){
        $session = $this->sessionVerify($request->get('dbid'),$request->get('sessionid'));
        if(is_null($session)){
            return 'AUTH_FAILED';
        }
        $pirep = Pirep::where('flight_id',$request->get('routeid'))->first();
        if(is_null($pirep)){
            $aircraft = Aircraft::where('registration',$request->get('aircraft'))->first();
            if(is_null($aircraft)){
                logger("Avion no existe");
                throw new \Exception("El avión a volar no existe");
            }
            $flight = Flight::where('id',$request->get('routeid'))->first();
            if(is_null($flight)){
                logger("Vuelo no existe");
                throw new \Exception("El vuelo a volar no existe");
            }
            $pirep = Pirep::fromFlight($flight);
            $pirep->flight_id = $flight->id;
            $pirep->user_id = $request->get('dbid');
            $pirep->aircraft_id = $aircraft->id;
            $pirep->flight_number = $request->get('flightnumber');
            $pirep->state = PirepState::DRAFT;
            $pirep->source_name = "SmartAcars";
            $pirep->source = PirepSource::ACARS;
            $pirep->save();

            $aircraft->state = AircraftState::IN_USE;
            $aircraft->save();
        }

        $acars = new Acars(['pirep_id' => $pirep->id,'status'=>PirepStatus::BOARDING]);
        $acars->lat = str_replace(",",".", $request->get('latitude')); //Convert float with , to float with .
        $acars->lon = str_replace(",",".", $request->get('longitude'));
        $acars->heading = $request->get('trueheading');
        $acars->altitude = $request->get('altitude');
        $acars->gs = $request->get('groundspeed');
        //$rpt->phase = $phase;
        //$rpt->client = 'SimAcars';
        $acars->distance =  $request->get('distanceremaining');
        //$rpt->timeremaining = $time_remain;
        //$rpt->online =  'NA';

        $acars->type = AcarsType::FLIGHT_PATH;
        //$acars->nav_type = AcarsType::FLIGHT_PATH;
        switch ((int)$request->get('phase')){
            case 0:
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::TAXI;
                $acars->status = PirepStatus::TAXI;
                break;
            case 2:
                //En pista
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::TAKEOFF;
                $acars->status = PirepStatus::TAKEOFF;
                break;
            case 2:
                //Innitial climb
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::INIT_CLIM;
                $acars->status = PirepStatus::INIT_CLIM;
                break;

            case 4:
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::ENROUTE;
                $acars->status = PirepStatus::ENROUTE;
                break;
            case 5:
                //Aterrizando
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::APPROACH;
                $acars->status = PirepStatus::APPROACH;
                break;
            case 7:
                //Approach
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::APPROACH;
                $acars->status = PirepStatus::APPROACH;
                break;
            case 8:
                //Landing
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::LANDING;
                $acars->status = PirepStatus::LANDING;
                break;
            case 8:
                //Landed
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::LANDED;
                $acars->status = PirepStatus::LANDED;
                break;

            case 9:
                //En tierra
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::ARRIVED;
                $acars->status = PirepStatus::ARRIVED;
                break;

            case 12:
                //Taxi to Gate
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::ARRIVED;
                $acars->status = PirepStatus::ARRIVED;
                break;

            default:
                logger("Fase ".(int)$request->get('phase'));
                $pirep->state = PirepState::IN_PROGRESS;
                $pirep->status = PirepStatus::SCHEDULED;
                $acars->status = PirepStatus::SCHEDULED;
                break;
        }


        $pirep->save();
        $acars->save();
        return "SUCCESS";

    }

    /**
     * @param $request
    (
    'route' => NULL,
    'comments' => NULL,
    'log' => 'smartCARS version 2.1.34.3, 2020/10/2 UTC[20:05:12] Preflight started, flying on VATSIM[20:05:13] Flying ef2000_v20 [20:05:38] Engine 1 is on[20:05:38] Engine 2 is on[20:06:37] Pushing back with 12230 lb of fuel[20:06:38] Taxiing to runway[20:06:51] Taking off[20:07:12] Gear lever raised[20:07:15] Climbing, pitch: 2, roll: 1 degrees right, 241 kts[20:07:18] Exceeded 250 KIAS below 10,000 feet MSL[20:07:21] Touched down early at -428 fpm, gear level: up, flaps: 0[20:07:21] On the ground 246 nm from the planned arrival airfield[20:07:22] Go around conditions met[20:07:31] Standard final approach conditions met[20:07:31] Go around conditions met[20:07:35] Standard final approach conditions met[20:07:38] Go around conditions met[20:07:41] Standard final approach conditions met[20:08:00] Go around conditions met[20:08:02] Standard final approach conditions met[20:08:04] Go around conditions met[20:08:08] Standard final approach conditions met[20:08:10] Go around conditions met[20:08:21] Speed corrected at 9969 ft after a max speed of 582 kts[20:08:22] Standard final approach conditions met[20:08:23] Go around conditions met[20:08:24] Standard final approach conditions met[20:09:26] Go around conditions met[20:09:41] Standard final approach conditions met[20:22:23] Go around conditions met[20:22:30] Standard final approach conditions met[20:25:02] Exceeded 250 KIAS below 10,000 feet MSL[20:26:29] Speed corrected at 6311 ft after a max speed of 296 kts[20:27:27] Flaps set to position 8 at 2505 ft at 181 kts[20:28:15] Gear lever lowered at 1813 ft at 161 kts[20:32:25] Touched down at -22 fpm, gear lever: down, pitch: 5, roll: level, 136 kts[20:32:25] On the ground 151 nm from the planned arrival airfield[20:32:25] Aircraft bounced, touched back down at -22 fpm[20:32:27] Aircraft bounced, touched back down at 63 fpm[20:32:29] Aircraft bounced, touched back down at 18 fpm[20:32:30] Aircraft bounced, touched back down at 64 fpm[20:32:57] Landed in 3996 ft, fuel: 5050 lb, weight: 33433 lb[20:32:57] Taxiing to gate[20:34:15] Flaps set to position 0[20:35:00] The flight may now be ended[20:35:00] Arrived, flight duration: 00:25',
    'action' => 'filepirep',
    'dbid' => '1',
    'sessionid' => 'HMw6WL48RxUjknlLNNfpxHiHyHKon73irwu9HwYI5JhI58Hj5Ki6Cvv23gbMyM7h',
    'code' => 'CH',
    'flightnumber' => '9999',
    'departureicao' => 'LEMD',
    'arrivalicao' => 'LEIB',
    'aircraft' => '1',
    'routeid' => 'VJm7X4eDpr4RdjLn',
    'bidid' => '10',
    'landingrate' => '-22',
    'fuelused' => '7250',
    'load' => '9587',
    'flighttime' => '00.25',
    )      *
     */
    private function filePirep($request){
        $session = $this->sessionVerify($request->get('dbid'),$request->get('sessionid'));
        if(is_null($session)){
            return 'AUTH_FAILED';
        }
        $pirep = Pirep::where('flight_id',$request->get('routeid'))->first();
        if(is_null($pirep)){
            return 'AUTH_FAILED';
        }
        $hs = explode(".",$request->get('flighttime'));
        $pirep->flight_time = ($hs[0]*60+$hs[1]);
        $pirep->fuel_used = $request->get('fuelused');
        $pirep->landing_rate = $request->get('landingrate');
        $pirep->zfw = $request->get('load');
        $pirep->state = PirepState::PENDING;
        $pirep->status = PirepStatus::ARRIVED;
        $pirep->save();

        $pirepComment = new PirepComment([
            'pirep_id' => $pirep->id,
            'user_id' => $pirep->user_id,
            'comment' => "Landing Rate | ".$request->get('landingrate')
        ]);
        $pirepComment->save();

        /**
         * @todo split log in differentes comments
         */
        $pirepComment = new PirepComment([
            'pirep_id' => $pirep->id,
            'user_id' => $pirep->user_id,
            'comment' => $request->get('log')
        ]);
        $pirepComment->save();

        // If flight it's charter remove
        if($pirep->flight->flight_type === FlightType::CHARTER_PAX_ONLY ||
            $pirep->flight->flight_type === FlightType::CHARTER_CARGO_MAIL ||
            $pirep->flight->flight_type === FlightType::CHARTER_SPECIAL
        ){
            $pirep->flight->active = 0;
            $pirep->flight->visible = 0;
            $pirep->flight->save();
        }
        $bid = Bid::where(['flight_id'=>$pirep->flight->id])->first();
        $bid->delete();

        $pirep->aircraft->state = AircraftState::PARKED;
        $pirep->aircraft->airport_id = $pirep->arr_airport;
        $pirep->aircraft->save();
        return "SUCCESS";
        //echo("ERROR");
    }
}
