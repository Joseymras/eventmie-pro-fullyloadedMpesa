<?php

namespace App\Http\Controllers\Eventmie;

use Classiebit\Eventmie\Http\Controllers\EventsController as BaseEventsController;
use App\Models\Event;
use App\Models\User;
use Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;
use App\Models\Ticket;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use App\Charts\ReviewChart;
use App\Services\PaystackService;

class EventsController extends BaseEventsController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        // CUSTOM
        $this->event      = new Event;
        $this->ticket     = new Ticket;
        $this->booking    = new Booking;
        // CUSTOM
    
    }
    /* ==================  EVENT LISTING ===================== */

    /**
     * Show all events
     *
     * @return array
     */
    public function index($view = 'vendor.eventmie-pro.events.index', $extra = [])
    {
        return parent::index($view, $extra);
    }
    /**
     * Show single event
     *
     * @return array
     */
    public function show(\Classiebit\Eventmie\Models\Event $event, $view = 'private_event.password', $extra = [])
    {   
        // CUSTOM
        $extra['is_stripe']            = 0;
        $extra['is_authorize_net']     = 0;
        $extra['is_bitpay']            = 0;
        $extra['is_stripe_direct']     = 0;
        $extra['is_twilio']            = 0;
        $extra['is_pay_stack']            = 0;
        $extra['stripe_secret_key']             = null;
        
        $extra['default_payment_method'] = $this->setDefaultPaymentMethod();
        
        if(!empty(setting('apps.stripe_public_key')) && !empty(setting('apps.stripe_secret_key')))
        {
            $extra['is_stripe']     = true;
            
            if(Auth::check())
            {
                $user = Auth::user();
                
                if(!empty(Auth::user()->is_manager))
                {
                    $user = User::find(Auth::user()->organizer_id);
                }

                $extra['stripe_secret_key'] =  $user->createSetupIntent()->client_secret;
            }

        }    
            

        if(!empty(setting('apps.authorize_transaction_key')) && !empty(setting('apps.authorize_login_id')) )
            $extra['is_authorize_net']     = true;

        if(!empty(setting('apps.bitpay_key_name')) && !empty(setting('apps.bitpay_encrypt_code')))
            $extra['is_bitpay'] = true;
        
        
        if(!empty(setting('apps.stripe_public_key')) && !empty(setting('apps.stripe_secret_key')) && !empty(setting('apps.stripe_direct')))
        {

            $extra['is_stripe_direct'] = $this->checkStripeAccount($event);
        }
            
        $paystack = app(PaystackService::class);
        if($paystack->isEnabled() && $paystack->isConfigured())
            $extra['is_pay_stack'] = true;
        

        if(!empty(setting('apps.twilio_sid')) && !empty(setting('apps.twilio_auth_token')) && !empty(setting('apps.twilio_number')))
            $extra['is_twilio']     = true;

        // sale tickets
        $sale_tickets = Ticket::where('sale_start_date', '<=', Carbon::now()->timezone(setting('regional.timezone_default'))->toDateTimeString())
        ->where('sale_end_date', '>', Carbon::now()->timezone(setting('regional.timezone_default'))->toDateTimeString())
        ->whereNotNull('sale_start_date')
        ->where(['event_id' => $event->id])
        ->orderBy('sale_start_date')
        ->get();    

        $extra['sale_tickets']  = $sale_tickets;

        $organiser = User::where(['id' => $event->user_id])->first();
        $extra['organiser'] = $organiser;

        //get reviews for customer
        $extra['reviews'] = null;
        $extra['take_reviews'] = false;

        //taking review and rating for customer
        if(Auth::check())
        {
            if(Auth::user()->hasRole('customer') && !empty($event->show_reviews) && !Auth::user()->bookings->where('event_id', $event->id)->isEmpty() )
            {
                // check customer review exist or not
                $extra['user_reviews'] = Auth::user()->reviews->where('event_id', $event->id)->first();

                //if have no login customer review then taking review button enable else disable
                if(empty($extra['user_reviews']))
                    $extra['take_reviews'] = true;
            }
        }

        //show events average review
        if($event->show_reviews)
        {
            $data = $this->averageReview($event->id);
        
            $extra['average_rating'] = $data['average_rating'];
            $extra['reviews']        = $data['reviews'];
        }

        // In case of private event
        if($event->event_password)
        {
            // check if event password already entered
            if(session('event_password_'.$event->id) == $event->event_password)
            {
                $view = "vendor.eventmie-pro.events.show";
            }

            // login first to access private event
            if(!Auth::check())
            {
                // set event url to redirect back
                $event_url = url()->current();
                session(['redirect_to_event'=>$event_url]);

                // if don't match password then will show error
                $msg = __('eventmie-pro::em.please_login');
                session()->flash('error', $msg);

                return redirect()->route('eventmie.login')->withErrors([__('eventmie-pro::em.please_login')]);    
            }
        }
        else
        {
            $view = "vendor.eventmie-pro.events.show";
        }

        return parent::show($event, $view, $extra);
    }

    // get tickets and it is public
    protected function get_tickets($event_id = null)
    {   
        $params    = [
            'event_id' =>  (int) $event_id,
        ];
        $tickets     = $this->ticket->get_event_tickets($params);
        
        // apply admin tax
        $tickets     = $this->admin_tax($tickets);

        // get the bookings by ticket for live availability check
        $bookedTickets  = $this->booking->get_seat_availability_by_ticket($params['event_id']);
        // make a associative array by ticket_id-event_start_date
        // to reduce the loops on Checkout popup
        $booked_tickets = [];
        foreach($bookedTickets as $key => $val)
        {
            // calculate total_vacant each ticket
            $ticket         = $tickets->where('id', $val->ticket_id)->first();

            // Skip if ticket not found or deleted
            if(!$ticket)
                continue;

            $booked_tickets["$val->ticket_id-$val->event_start_date"] = $val;

            // min 0 or else it'll throw JS error
            $total_vacant   = $ticket->quantity - $val->total_booked;
            $total_vacant   = $total_vacant < 0 ? 0 : $total_vacant;
            $booked_tickets["$val->ticket_id-$val->event_start_date"]->total_vacant = $total_vacant;

            // unset if total_vacant > global max_ticket_qty
            // in case of high values, it throw JS error
            $max_ticket_qty = (int) setting('booking.max_ticket_qty');
            if($total_vacant > $max_ticket_qty)
                unset($booked_tickets["$val->ticket_id-$val->event_start_date"]);
        }

        // sum all ticket's capacity
        $total_capacity = 0;
        foreach($tickets as $val)
            $total_capacity += $val->quantity;
        
        //CUSTOM
        $currency       = setting('regional.currency_default');
        // get event by event_id
        $event          = $this->event->get_event(null, $event_id);
        
        if(!empty($event->currency))
            $currency = $event->currency;

        //CUSTOM

        return [
            'tickets' => $tickets, 
            // 'currency' => setting('regional.currency_default'), 
            //CUSTOM
            'currency' => $currency, 
            //CUSTOM
            'booked_tickets'=>$booked_tickets,
            'total_capacity'=>$total_capacity,
        ];
    }


    // EVENT LISTING APIs
    // get all events
    public function events(Request $request)
    {
        $filters         = [];
        // call event fillter function
        $filters         = $this->event_filters($request);

        $events          = $this->event->events($filters);
        
        $event_ids       = [];

        foreach($events as $key => $value)
            $event_ids[] = $value->id;

        // pass events ids
        // tickets
        $events_tickets     = $this->ticket->get_events_tickets($event_ids);

        $events_data             = [];
        foreach($events as $key => $value)
        {
            // online event - yes or no
            $value                  = $value->makeVisible('online_location');
            // check event is online or not
            $value->online_location    = (!empty($value->online_location)) ? 1 : 0; 

            $events_data[$key]             = $value;
            
           foreach($events_tickets as $key1 => $value1)
            {
                // check relevant event_id with ticket id
                if($value->id == $value1['event_id'])
                {
                    $events_data[$key]->tickets[]       = $value1;
                }
            }

            //CUSTOM
            // sale tickets
            $sale_tickets = Ticket::where('sale_start_date', '<=', Carbon::now()->timezone(setting('regional.timezone_default'))->toDateTimeString())
            ->where('sale_end_date', '>', Carbon::now()->timezone(setting('regional.timezone_default'))->toDateTimeString())
            ->whereNotNull('sale_start_date')
            ->where(['event_id' => $value->id])
            ->orderBy('sale_start_date')
            ->get();    

            $events_data[$key]->sale_tickets  = $sale_tickets;
            //CUSTOM
        }
        
        // set pagination values
        $event_pagination = $events->jsonSerialize();

        // get all countries
        $data = $this->country->get_countries_having_events($filters['country_id']);
        
        $countries = $data['countries'];
        $states    = $data['states'];
        $cities    = $data['cities'];
        
        return response([
            'events'=> [
                'currency' => setting('regional.currency_default'),
                'data' => $events_data,
                'total' => $event_pagination['total'],
                'per_page' => $event_pagination['per_page'],
                'current_page' => $event_pagination['current_page'],
                'last_page' => $event_pagination['last_page'],
                'from' => $event_pagination['from'],
                'to' => $event_pagination['to'],
                'countries' => $countries,
                'cities'    => $cities,
                'states'    => $states
            ],
        ], Response::HTTP_OK);
    }

    /**
     *  set default payment method
     */

    protected function setDefaultPaymentMethod()
    {
        $default_payment_method  = !empty($this->is_paypal()) ? 1 : 0;
        
        if(empty($default_payment_method))
        {

            if(!empty(setting('apps.stripe_public_key')) && !empty(setting('apps.stripe_secret_key')))
                $default_payment_method     = 2;
        
            if(!empty(setting('apps.authorize_transaction_key')) && !empty(setting('apps.authorize_login_id')) )
                $default_payment_method     = 3;

            if(!empty(setting('apps.bitpay_key_name')) && !empty(setting('apps.bitpay_encrypt_code')))
                $default_payment_method     = 4;

            if(!empty(setting('apps.stripe_public_key')) && !empty(setting('apps.stripe_secret_key')) && !empty(setting('apps.stripe_direct')))
                $default_payment_method     = 5;

            if(!empty(setting('apps.paystack_public_key')) && !empty(setting('apps.paystack_secret_key')) && !empty(setting('apps.paystack_merchant_email')))
                $default_payment_method     = 6;
        }    

        return $default_payment_method;
        
    }

    /**
     *  check stripe connected account is verified or not
     */
    
    protected function checkStripeAccount(\Classiebit\Eventmie\Models\Event $event)
    {
        $stripe_account_id = User::where(['id' => $event->user_id])->first()->stripe_account_id;

        if(empty($stripe_account_id))
            return false;

        $stripe = new \Stripe\StripeClient(
            setting('apps.stripe_secret_key')
          );
        
        $stripe_account = $stripe->accounts->retrieve(
            $stripe_account_id,
            []
        );

        if(empty($stripe_account))
            return false;

            
        if(empty($stripe_account->charges_enabled) || empty($stripe_account->payouts_enabled))
        {
            return false;
        }

        return true;
        
    }


    protected function averageReview($event_id = null)
    {
        // 5 star - 252
        // 4 star - 124
        // 3 star - 40
        // 2 star - 29
        // 1 star - 33
        // (5*252 + 4*124 + 3*40 + 2*29 + 1*33) / (252+124+40+29+33) = 4.11 and change

        $reviews = \App\Models\Event::with(['reviews' => function($query) use($event_id) {
                    $query->where('status' , 1)->orderBy('updated_at', 'desc');
                }, 'reviews.user'])->where(['id' => $event_id])->first()->reviews; 

        $average_rating   =  0;
        $average_count    =  [];
        
        if($reviews->isNotEmpty())
        {
            $group_rating = $reviews->groupBy('rating');

            $multiplied = $group_rating->map(function ($item, $key)  {
            
                return $item->sum('rating') * (int)$item[0]->rating;
                
            })->flatten();

            $average_rating = round($multiplied->sum() / $reviews->sum('rating'));

        }

        
        for($i = 5; $i >= 1; $i--)
        {
            $average_count[$i] = $reviews->where('rating', $i)->count();
        }

        $average_count = collect($average_count)->flatten()->all();
        
        $reviews = \App\Models\Event::with(['reviews' => function($query) use($event_id) {
            $query->where('status' , 1);
        }, 'reviews.user'])->where(['id' => $event_id])->first();
        
        $reviews = $reviews->reviews()->where('status', 1)->paginate(10);
        

        
        return ['average_rating' => $average_rating, 'reviews' => $reviews];
    }
    

    
}
