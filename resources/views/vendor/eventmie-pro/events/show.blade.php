@extends('eventmie::layouts.app')

@section('title', $event->title)
@section('meta_title', $event->meta_title)
@section('meta_keywords', $event->meta_keywords)
@section('meta_description', $event->meta_description)
@section('meta_image', '/storage/'.$event['thumbnail'])
@section('meta_url', url()->current())

@section('stylesheet')
<link rel="canonical" href="{{ route('eventmie.events_show', [$event->slug]) }}">
@php
    $eventSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->title,
        'description' => strip_tags((string) $event->excerpt),
        'startDate' => \Illuminate\Support\Carbon::parse($event->start_date.' '.$event->start_time)->toIso8601String(),
        'endDate' => \Illuminate\Support\Carbon::parse($event->end_date.' '.$event->end_time)->toIso8601String(),
        'eventStatus' => 'https://schema.org/EventScheduled',
        'image' => [url('/storage/'.$event->thumbnail)],
        'url' => route('eventmie.events_show', [$event->slug]),
        'location' => [
            '@type' => 'Place',
            'name' => $event->venue ?: $event->city,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $event->city,
                'addressCountry' => 'KE',
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($eventSchema, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
@endsection

@section('content')

<!--BANNER-->
<section>
    <div class="lgx-banner event-poster background-tint" style="background-image: url({{ '/storage/'.$event['poster'] }});">
        <div class="lgx-banner-style">
            <div class="lgx-inner lgx-inner-fixed">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="lgx-banner-info-area">
                                <div class="lgx-banner-info text-center">
                                    <h2 class="title">&nbsp;</h2>
                                    <h3 >&nbsp;</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--//.ROW-->
                </div>
                <!-- //.CONTAINER -->
            </div>
            <!-- //.INNER -->
        </div>
    </div>
</section>
<!--BANNER END-->

<!--ABOUT-->
<section>
    <div id="lgx-about" class="lgx-about">
        <div class="mt-30 mb-50 mt-mobile-0">
            <div class="container-fluid">
                <div class="row">

                    <div class="col-md-4 offset-md-1 visible-lg visible-md">
                        <div class="lgx-banner-info-area">
                            <div class="lgx-banner-info-circle lgx-info-circle">
                                <div class="info-circle-inner" style="background-image: url({{ eventmie_asset('img/bg-wave-circle.png') }});">
                                    <h3 class="date">
                                        {{ userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', 'd') }} 

                                        <span>
                                            {{ userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', 'M-Y') .'('. showTimezone() .')' }} 
                                        </span>
                                    </h3>
                                    <div class="lgx-countdown-area">
                                        <!-- Date Format :"Y/m/d" || For Example: 1017/10/5  -->
                                        <div id="lgx-countdown" 
                                            data-date="{{userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', 'Y/m/d')}} ">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="lgx-about-content-area">
                            <div class="lgx-heading">
                                <h2 class="heading">{{ $event['title'] }}</h2>
                                <h3 class="subheading">
                                    
                                    {{-- CUSTOM --}}
                                    @php $organiser_slug = $extra['organiser']->name; @endphp
                                    @if($extra['organiser']->organisation)
                                    <a class="text-primary" href="{{route('organiser_show',[$organiser_slug]) }}">@lang('eventmie-pro::em.by') {{ $extra['organiser']->organisation }}</a>
                                    @endif
                                    {{-- CUSTOM --}}
                                    @if(!empty($event['online_location']))
                                        <span class="lgx-badge lgx-badge-online"><i class="fas fa-signal"></i>&nbsp; @lang('eventmie-pro::em.online_event')</span>
                                    @endif
                                    
                                    <span class="lgx-badge lgx-badge-primary">{{ $category['name'] }}</span>

                                    @if(!empty($free_tickets))
                                        <span class="lgx-badge lgx-badge-primary">@lang('eventmie-pro::em.free_tickets')</span>
                                    @endif

                                    @if($event->repetitive)
                                        @if($event->repetitive_type == 1)
                                            <span class="lgx-badge lgx-badge-primary">
                                                @lang('eventmie-pro::em.repetitive_daily_event')
                                            </span>
                                        @elseif($event->repetitive_type == 2)    
                                            <span class="lgx-badge lgx-badge-primary">
                                                @lang('eventmie-pro::em.repetitive_weekly_event')
                                            </span>
                                        @elseif($event->repetitive_type == 3)    
                                            <span class="lgx-badge lgx-badge-primary">
                                                @lang('eventmie-pro::em.repetitive_monthly_event')
                                            </span>
                                        @endif    
                                        
                                    @endif
                                    
                                    @if($ended)   
                                        <span class="lgx-badge lgx-badge-danger">@lang('eventmie-pro::em.event_ended')</span>
                                    @endif
                                </h3>

                                <h3 class="subheading share-btns">
                                    <span><strong>@lang('eventmie-pro::em.share_event') &nbsp;</strong></span>

                                    <span><a class="btn btn-sm" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u={{ url()->current() }}"><i class="fab fa-facebook-square"></i></a></span>
                                    <span><a class="btn btn-sm" target="_blank" href="https://twitter.com/intent/tweet?text={{ urlencode($event->title) }}&url={{ url()->current() }}"><i class="fab fa-twitter"></i></a></span>
                                    <span><a class="btn btn-sm" target="_blank" href="http://www.linkedin.com/shareArticle?mini=true&url={{ url()->current() }}&title={{ urlencode($event->title) }}"><i class="fab fa-linkedin"></i></a></span>
                                    <span><a class="btn btn-sm" target="_blank" href="https://wa.me/?text={{ url()->current() }}"><i class="fab fa-whatsapp"></i></a></span>
                                    <span><a class="btn btn-sm" target="_blank" href="https://www.reddit.com/submit?title={{ urlencode($event->title) }}&url={{ url()->current() }}"><i class="fab fa-reddit"></i></a></span>
                                    <span><a class="btn btn-sm" href="javascript:void(0)" onclick="copyToClipboard()"><i class="fas fa-link"></i></a></span>
                                </h3>

                                <a class="lgx-btn lgx-btn-red mt-2" href="#buy-tickets"><i class="fas fa-ticket-alt"></i> @lang('eventmie-pro::em.get_tickets')</a>
                                
                            </div>
                            <div class="lgx-about-content">{!! $event['description'] !!}</div>
                        </div>
                    </div>

                </div>
                <br><br>
                <div class="row">
                    <div class="col-12 col-sm-5 col-md-5 offset-md-1">
                        <div class="lgx-about-service">
                            <div class="lgx-single-service lgx-single-service-color">
                                <div class="text-area">
                                    <span class="icon col-white"><i class="fas fa-map-marked-alt" aria-hidden="true"></i></span>
                                    <h2 class="title col-white">@lang('eventmie-pro::em.where')</h2>
                                    <p>
                                        @if(!empty($event['online_location']))
                                            <strong>@lang('eventmie-pro::em.online_event')</strong> <br>
                                        @endif

                                        <strong>{{$event->venue}}</strong> <br>
                                        
                                        @if($event->address)
                                        {{$event->address}} {{ $event->zipcode }} <br>
                                        @endif
                                        
                                        @if($event->city)
                                        {{ $event->city }}, 
                                        @endif
                                        
                                        @if($event->state)
                                        {{ $event->state }}, 
                                        @endif
                                        
                                        @if($country)
                                            {{ $country->get('country_name') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-5 col-md-5">
                        <div class="lgx-about-service">
                             <div class="lgx-single-service lgx-single-service-color">
                                 <div class="text-area">
                                    <span class="icon col-white"><i class="fas fa-stopwatch" aria-hidden="true"></i></span>
                                    <h2 class="title col-white">@lang('eventmie-pro::em.when')</h2>
                                    
                                    @if(!$event->repetitive)
                                    <p>
                                        {{ userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', format_carbon_date(false)) }}
                                        {{ '('. showTimezone() .')' }}
                                        <br>@lang('eventmie-pro::em.till')<br>

                                        {{ userTimezone($event->end_date.' '.$event->end_time, 'Y-m-d H:i:s', format_carbon_date(false)) }}

                                        {{ '('. showTimezone() .')' }}
                                    </p>
                                    @else
                                    <p>
                                        {{ userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', format_carbon_date(true)) }}
                                        <br>@lang('eventmie-pro::em.till')<br>
                                        {{ userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', 'Y-m-d') <= userTimezone($event->end_date.' '.$event->end_time, 'Y-m-d H:i:s', 'Y-m-d') ? userTimezone($event->end_date.' '.$event->end_time, 'Y-m-d H:i:s', format_carbon_date(true)) : userTimezone($event->start_date.' '.$event->start_time, 'Y-m-d H:i:s', format_carbon_date(true))}}
                                    </p>
                                    @endif
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- //.CONTAINER -->
        </div><!-- //.INNER -->
    </div>
</section>
<!--ABOUT END-->
{{-- CUSTOM --}}
{{-- Seating Chart Image --}}
@if($event->seatingchart_image)
<section>
    <div id="lgx-schedule" class="lgx-schedule lgx-schedule-light">
        <div class="lgx-inner" style="background-image: url({{ eventmie_asset('img/bg-pattern.png') }});">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="lgx-heading">
                            <h2 class="heading">@lang('eventmie-pro::em.seating_chart')</h2>
                        </div>
                    </div>
                </div>
                <!--//main row-->
                <div class="row">
                    <div class="col-12 text-center">
                        <img src="/storage/{{ $event->seatingchart_image }}" alt="{{ $event->title }}"/>
                    </div>
                    <!--//col-->
                </div>
            </div>
            <!--//container-->
        </div>
    </div>
</section>
@endif


{{-- CUSTOM --}}
<!--SCHEDULE-->
<section>
    <div id="buy-tickets" class="lgx-schedule">
        <div class="lgx-inner">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-registration-area-simple">
                            <div class="lgx-heading lgx-heading-white">
                                <h2 class="heading">@lang('eventmie-pro::em.get_tickets')</h2>
                                <h3 class="subheading">@lang('eventmie-pro::em.select_schedule')</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <select-dates 
                        :event="{{ json_encode($event, JSON_HEX_APOS) }}" 
                        :max_ticket_qty="{{ json_encode($max_ticket_qty, JSON_HEX_APOS) }}"
                        :login_user_id="{{ json_encode(\Auth::id(), JSON_HEX_APOS) }}"
                        :is_customer="{{ Auth::id() ? (Auth::user()->hasRole('customer') ? 1 : 0) : 1 }}"
                        :is_organiser="{{ Auth::id() ? (Auth::user()->hasRole('organiser') || Auth::user()->hasRole('pos') ? 1 : 0) : 0 }}"
                        :is_pos="{{ Auth::id() ? (Auth::user()->hasRole('pos') ? 1 : 0) : 0 }}"
                        :is_admin="{{ Auth::id() ? (Auth::user()->hasRole('admin') ? 1 : 0) : 0 }}"
                        :is_paypal="{{ $is_paypal }}"
                        :is_offline_payment_organizer="{{ setting('booking.offline_payment_organizer') ? 1 : 0 }}"
                        :is_offline_payment_customer="{{ setting('booking.offline_payment_customer') ? 1 : 0}}"
                        :tickets="{{ json_encode($tickets, JSON_HEX_APOS) }}"
                        :booked_tickets="{{ json_encode($booked_tickets, JSON_HEX_APOS) }}"
                        :currency="{{ json_encode($currency, JSON_HEX_APOS) }}"
                        :total_capacity="{{ $total_capacity }}"
                        :date_format="{{ json_encode([
                            'vue_date_format' => format_js_date(),
                            'vue_time_format' => format_js_time()
                        ], JSON_HEX_APOS) }}"
                    >
                    </select-dates>
                </div>
                <!--//.ROW-->
            </div>
            <!-- //.CONTAINER -->
        </div>
        <!-- //.INNER -->
    </div>
</section>
<!--SCHEDULE END-->

<!--Event FAQ-->
@if($event['faq'])
<section>
    <div id="lgx-about" class="lgx-about">
        <div class="lgx-inner">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-heading">
                            <h2 class="heading">@lang('eventmie-pro::em.event_info')</h2>
                        </div>
                    </div>
                    <!--//main COL-->
                </div>
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-about-content-area text-center">
                            <div class="lgx-about-content">{!! $event['faq'] !!}</div>
                        </div>
                    </div>
                </div>
                <!--//.ROW-->
            </div>
            <!-- //.CONTAINER -->
        </div>
    </div>
</section>
@endif
<!--Event FAQ END-->

<!--TAGS-->
@php $i = 0; @endphp
@foreach($tag_groups as $key => $group)
@php $i++; @endphp
<section>
    <div id="lgx-schedule-tag" class="{{ ($i%2) ? 'lgx-schedule lgx-schedule-dark' : '' }}">
        <div class="lgx-inner" style="{{ ($i%2) ? 'background-image: url('.eventmie_asset('img/bg-pattern.png').');' : '' }}">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-heading {{ ($i%2) ? 'lgx-heading-white' : '' }}">
                            <h2 class="heading"> {{ ucfirst($key) }}</h2>
                        </div>
                    </div>
                </div>
                <!--//.ROW-->
                <div class="row justify-content-center">
                @foreach($group as $key1 => $value)
                    <div class="col-xs-12 col-sm-6 col-md-4">
                        <div class="lgx-single-speaker">
                                
                                @if($value['is_page'] > 0)
                                <a href="{{ route('eventmie.events_tags',[$event->slug, str_replace(' ', '-', $value['title'])] ) }}">
                                @elseif($value['website'])
                                <a href="{{ $value['website'] }}" target="_blank">
                                @endif

                                    @if($value['image'])
                                    <img src="/storage/{{ $value['image'] }}" alt="{{ $value['title'] }}"/>
                                    @else
                                    <img src="{{ eventmie_asset('img/512x512.jpg') }}" alt="{{ $value['title'] }}"/>
                                    @endif

                                @if($value['is_page'] > 0 || $value['website'])
                                </a>
                                @endif
                            <figure>    
                                <figcaption>
                                    @if($value['is_page'] > 0)
                                    <div class="social-group">
                                        <a class="sp-tw" href="{{ $value['twitter'] }}" target="_blank"><i class="fab fa-twitter"></i></a>
                                        <a class="sp-fb" href="{{ $value['facebook'] }}" target="_blank"><i class="fab fa-facebook"></i></a>
                                        <a class="sp-insta" href="{{ $value['instagram'] }}" target="_blank"><i class="fab fa-instagram"></i></a>
                                        <a class="sp-in" href="{{ $value['linkedin'] }}" target="_blank"><i class="fab fa-linkedin"></i></a>
                                        <a class="sp-in" href="{{ $value['website'] }}" target="_blank"><i class="fas fa-globe"></i></a>
                                    </div>
                                    @endif

                                    <div class="speaker-info">
                                        <h3 class="title">
                                            @if($value['is_page'] > 0)
                                                <a href="{{ route('eventmie.events_tags',[$event->slug, str_replace(' ', '-', $value['title'])] ) }}">{{$value['title']}}</a>
                                            @elseif($value['website'])
                                                <a href="{{ $value['website'] }}" target="_blank">{{$value['title']}}</a>
                                            @else
                                                {{$value['title']}}
                                            @endif
                                        </h3>

                                        @if($value['sub_title'])
                                        <h4 class="subtitle">{{$value['sub_title']}}</h4>
                                        @endif
                                    </div>

                                </figcaption>

                            </figure>
                        </div>
                    </div>
                @endforeach
                </div>
            </div>
            <!-- //.CONTAINER -->
        </div>
        <!-- //.INNER -->
    </div>
</section>
@endforeach
<!--Tags END-->


<!--PHOTO GALLERY-->
@if(!empty($event->images))
<section>
    <div id="lgx-photo-gallery" class="lgx-gallery-popup lgx-photo-gallery-normal lgx-photo-gallery-black">
        <div class="lgx-inner">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-heading lgx-heading-white">
                            <h2 class="heading">@lang('eventmie-pro::em.event_gallery')</h2>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <gallery-images :gimages="{{ $event->images }}" ></gallery-images>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif
<!--PHOTO GALLERY END-->

<!--Event Video-->
@if(!empty($event->video_link))
<section>
    <div id="lgx-travelinfo" class="lgx-travelinfo">
        <div class="lgx-inner">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-heading">
                            <h2 class="heading">@lang('eventmie-pro::em.watch_trailer')</h2>
                        </div>
                    </div>
                    <!--//main COL-->
                </div>
                <div class="row">
                    {{-- CUSTOM --}}
                    @foreach (json_decode($event->video_link) as $item)
                        @if(count(json_decode($event->video_link)) == 1)
                        <div class="col-md-offset-1 col-md-10">
                            <iframe src="https://www.youtube.com/embed/{{ $item }}" allowfullscreen style="width: 100%; height: 500px; border-radius: 16px; border: none;"></iframe>
                        </div>
                        @else
                        <div class="col-md-6 mb-5">
                            <iframe src="https://www.youtube.com/embed/{{ $item }}" allowfullscreen style="width: 100%; height: 300px; border-radius: 16px; border: none;"></iframe>
                        </div>
                        @endif
                    @endforeach
                    {{-- CUSTOM --}}
                </div>
                <!--//.ROW-->
            </div>
            <!-- //.CONTAINER -->
        </div>
    </div>
</section>
@endif
<!--Event Video END-->
<!--Event Video END-->

@if($event->show_reviews)
<section>
    <div id="lgx-travelinfo" class="lgx-travelinfo">
        <div class="lgx-inner">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="lgx-heading">
                            <h2 class="heading">@lang('eventmie-pro::em.rating_review')</h2>
                        </div>
                    </div>
                    <!--//main COL-->
                </div>
                <div class="row">
                    <div class="offset-md-1 col-md-10">
                        @include('vendor.eventmie-pro.events.custom.average_rating')
                    </div>
                </div>
            </div>
            <!-- //.CONTAINER -->
        </div>
    </div>
</section>
@endif

<!--GOOGLE MAP-->
@if($event->latitude && $event->longitude)
{{--  CUSTOM --}}
<section>
    
    <div class="container">
        <div class="row">
            <div class="col-md-12 text-center">
                <a href="{{'https://www.google.com/maps/search/'.$event->address.'/'.$event->latitude.','.$event->longitude}}" class="lgx-btn lgx-btn-primary mt-5" id="get_directions"><i class="fas fa-location-arrow"></i> @lang('eventmie-pro::em.get_directions')</a>
            </div>
        </div>
    </div>
</section>
<br>
{{--  CUSTOM --}}
<div class="innerpage-section g-map-wrapper">
    <div class="lgxmapcanvas map-canvas-default"> 
        
        {{-- <g-component :lat="{{ json_encode($event->latitude, JSON_HEX_APOS) }}" :lng="{{ json_encode($event->longitude, JSON_HEX_APOS) }}" >
        </g-component> --}}
        {{--  CUSTOM --}}
        <div id="warnings-panel"></div>
        <div id="map" style="height: 100%"></div>
        {{--  CUSTOM --}}

    </div>
</div>
@endif
<!--GOOGLE MAP END-->

@endsection

@section('javascript')
<script type="text/javascript">
    var google_map_key           = {!! json_encode( $google_map_key) !!};
    
    var stripe_publishable_key   = {!! json_encode(setting('apps.stripe_public_key')) !!};
    
    var stripe_secret_key        = {!! json_encode( $extra['stripe_secret_key']  )  !!};
    
    var is_stripe                = {!! json_encode( $extra['is_stripe']) !!};

    var is_authorize_net         = {!! json_encode( $extra['is_authorize_net']) !!};

    var is_bitpay                = {!! json_encode( $extra['is_bitpay']) !!};

    var is_stripe_direct         = {!! json_encode( $extra['is_stripe_direct']) !!};
    
    var is_twilio                = {!! json_encode( $extra['is_twilio']) !!};

    var default_payment_method   = {!! json_encode( $extra['default_payment_method']) !!};

    var sale_tickets             = {!! json_encode( $extra['sale_tickets']) !!};

    var is_pay_stack              = {!! json_encode( $extra['is_pay_stack']) !!};
    
</script>

<script src="https://cdn.jsdelivr.net/npm/v-mask/dist/v-mask.min.js"></script>
<script type="text/javascript" src="{{ asset('js/events_show_v1.8.js') }}"></script>

{{-- CUSTOM --}}
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>

<script async defer src="https://maps.googleapis.com/maps/api/js?key={{setting('apps.google_map_key')}}&callback=initMap"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.23.0/axios.min.js" integrity="sha512-Idr7xVNnMWCsgBQscTSCivBNWWH30oo/tzYORviOCrLKmBaRxRflm2miNhTFJNVmXvCtzgms5nlJF4az2hiGnA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">

var latitude        = {!! json_encode($event->latitude) !!};
var longitude       = {!! json_encode($event->longitude) !!};
var venue           = {!! json_encode($event->venue) !!};


function initMap() {
    
    var markerArray = [];

    // Instantiate a directions service.
    var directionsService = new google.maps.DirectionsService;

    // Create a map and center it on Manhattan.
    var map = new google.maps.Map(document.getElementById('map'), {
        zoom: 13,
        center: {lat:  parseFloat(latitude), lng:  parseFloat(longitude)}
    });

    var marker = new google.maps.Marker({
        position: {lat:  parseFloat(latitude), lng:  parseFloat(longitude)},
        title: venue,
    });

    // To add the marker to the map, call setMap();
    marker.setMap(map);

    // Create a renderer for directions and bind it to the map.
    var directionsDisplay = new google.maps.DirectionsRenderer({map: map});

    // Instantiate an info window to hold step text.
    var stepDisplay = new google.maps.InfoWindow;

    // Listen to change events from the start and end lists.
    var onChangeHandler = function() {
        // get current location latlngs
        getUserLocationLatLong(directionsDisplay, directionsService, markerArray, stepDisplay, map);  
    };
    document.getElementById('get_directions').addEventListener('click', onChangeHandler);
    
}

let infoWindow;
function getUserLocationLatLong(directionsDisplay, directionsService, markerArray, stepDisplay, map) {

    console.log('heyy');
    // map = new google.maps.Map(document.getElementById("map"), {
    //     zoom: 13,
    //     center: {lat:  parseFloat(latitude), lng:  parseFloat(longitude)}
    // });
    infoWindow = new google.maps.InfoWindow();

    infoWindow = new google.maps.InfoWindow();

    infoWindow = new google.maps.InfoWindow();

    // Try HTML5 geolocation.
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
        position => {
            const pos = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            calculateAndDisplayRoute(directionsDisplay, directionsService, markerArray, stepDisplay, map, position.coords.latitude, position.coords.longitude);
        }, 
        () => {
            alert('Error-1');
        }
        );
    } else {
        // Browser doesn't support Geolocation
        alert('Browser doesnt support Geolocation');
    }

    console.log('heyy');
    console.log('heyy');
}
    


function calculateAndDisplayRoute(directionsDisplay, directionsService, markerArray, stepDisplay, map, cur_lat, cur_lng) {
    // First, remove any existing markers from the map.
    for (var i = 0; i < markerArray.length; i++) {
        markerArray[i].setMap(null);
    }

    directionsService.route({
        origin: new google.maps.LatLng(parseFloat(cur_lat), parseFloat(cur_lng)),
        destination: new google.maps.LatLng(parseFloat(latitude), parseFloat(longitude)),
        travelMode: 'DRIVING',
        drivingOptions: {
            departureTime: new Date(/* now, or future date */),
            trafficModel: google.maps.TrafficModel.BEST_GUESS
        },
    }, function(response, status) {
        console.log(response);
        if (status === 'OK') {
            document.getElementById('warnings-panel').innerHTML =
                '<b>' + response.routes[0].warnings + '</b>';
            directionsDisplay.setDirections(response);
            showSteps(response, markerArray, stepDisplay, map);
        } else {
            window.alert(trans('em.fail_directions'));
        }
    });
}

function showSteps(directionResult, markerArray, stepDisplay, map) {
    var myRoute = directionResult.routes[0].legs[0];
    for (var i = 0; i < myRoute.steps.length; i++) {
    var marker = markerArray[i] = markerArray[i] || new google.maps.Marker;
    marker.setMap(map);
    marker.setPosition(myRoute.steps[i].start_location);
    attachInstructionText(
        stepDisplay, marker, myRoute.steps[i].instructions, map);
    }
}

function attachInstructionText(stepDisplay, marker, text, map) {
    google.maps.event.addListener(marker, 'click', function() {
        stepDisplay.setContent(text);
        stepDisplay.open(map, marker);
    });
}



function open(){
    document.getElementById("review_modal").style.display = "block";
}


</script>
{{-- CUSTOM  --}}
@stop
