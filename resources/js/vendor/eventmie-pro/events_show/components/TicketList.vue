<template>
    <div>
        <div class="modal modal-mask" v-if="openModal">
            <div class="modal-dialog modal-container modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" @click="close()"><span aria-hidden="true">&times;</span></button>
                        <div class="text-center">
                            <h3 class="title ticket-selected-text mb-4">{{ trans('em.checkout') }}</h3>
                        </div>
                    </div>
                    
                    <form ref="form" @submit.prevent="validateForm" method="POST" >
                        <!-- CUSTOM -->
                        <div class="form-group pl-3" v-if="is_admin > 0">
                            <input type="checkbox" class="custom-control-input" :value=1 name="is_bulk" v-model="is_bulk" >
                            <label class="custom-control-label" >{{ trans('em.complimentary_bookings') }}</label>
                        </div>
                        <!-- CUSTOM -->
                        
                        <input type="hidden" class="form-control" name="event_id" :value="tickets[0].event_id" >
                        <input type="hidden" class="form-control" name="booking_date" :value="serverTimezone(booking_date+' '+start_time, 'dddd LL HH:mm a').format('YYYY-MM-DD')" >

                        <input type="hidden" class="form-control" name="booking_end_date" 
                            :value="(booking_end_date != null && typeof(booking_end_date) != 'undefined') ? 
                            serverTimezone(booking_end_date+' '+end_time, 'dddd LL HH:mm a').format('YYYY-MM-DD') : null" 
                        >
                         <input type="hidden" class="form-control" name="start_time" :value="serverTimezone(booking_date+' '+start_time, 'dddd LL HH:mm a').format('HH:mm:ss')" >
                        <input type="hidden" class="form-control" name="end_time" :value="serverTimezone((booking_end_date == null ? booking_date : booking_end_date) +' '+end_time, 'dddd LL HH:mm a').format('HH:mm:ss')" >
                        
                        <input type="hidden" class="form-control" name="merge_schedule" :value="event.merge_schedule" >
                        <input type="hidden" name="customer_id" v-model="customer_id" v-validate="'required'" >
                        
                        <div class="row" v-if="is_customer <= 0 && is_bulk <= 0 && is_pos <= 0">
                            <!-- CUSTOM -->
                            <div class="col-md-5 mb-5 ml-4" >
                                <label for="customer_id">{{ trans('em.add') }} {{ trans('em.new') }} {{ trans('em.attendee') }}</label><br>
                                <button class="btn lgx-btn lgx-btn-black lgx-btn-sm" type="button"  @click="add_attendee = 1"><i class="fas fa-user-plus"></i> {{ trans('em.create')+' '+trans('em.attendee') }}</button>
                                <!-- add attendee -->
                                <add-attendee v-if="add_attendee > 0" :add_attendee="add_attendee"></add-attendee >
                            </div>
                            <!-- CUSTOM -->
                        </div>

                        <!-- CUSTOM -->
                        <div class="row" v-if="is_twilio > 0">
                            <div class="col-md-12 mb-5" >
                                <input type="hidden" class="form-control"  name="phone_t" v-model="phone_t" >
                                <div class="col-md-5" v-if="customer != null && customer.phone == null ">
                                    <label>{{ trans('em.phone') + '('+ trans('em.required') +')' }}</label>
                                    <input type="text" class="form-control"  name="phone_t" v-model="phone_t" v-validate="'required'" :placeholder="trans('em.phone_info')">
                                    <span v-show="errors.has('phone_t')" class="help text-danger">{{ errors.first('phone_t') }}</span>
                                </div>
                            </div>    
                        </div>
                        <!-- CUSTOM -->

                        <div class="row">

                            <!-- only for admin & organizer -->
                            <!-- <div class="col-md-12 mb-5" v-if="is_customer <= 0"> -->
                            
                            <!-- CUSTOM -->
                            <div class="col-md-12 mb-5" >
                            <!-- <div class="col-md-5"
                           
                            > -->
                                <!-- <div class="col-md-5"> -->
                                <div class="col-md-5"
                                v-if="is_customer <= 0 && is_bulk <= 0"
                                >
                                <!-- CUSTOM -->  
                                    
                                    <label for="customer_id">{{ trans('em.select_customer') }}</label>
                                
                                    <v-select 
                                        label="name" 
                                        class="style-chooser" 
                                        :placeholder="trans('em.search_customer_email')"
                                        v-model="customer" 
                                        :required="!customer" 
                                        :filterable="false" 
                                        :options="options" 
                                        @search="onSearch" 
                                    ><div slot="no-options">{{ trans('em.customer_not_found') }}</div></v-select>
                                    
                                    <div class="invalid-feedback danger" v-show="errors.has('customer_id')">{{ errors.first('customer_id') }}</div>
                                </div>
                                 
                            </div>

                            <!-- Booking Info -->
                            <div class="col-md-12">
                                <p class="mb-0 lead lead-caption text-center">{{ trans('em.booking_info') }}</p>

                                <ul class="list-group m-0">
                                    <li class="list-group-item d-flex justify-content-between lh-condensed d-flex-wrap">
                                        
                                        <div>
                                            <h6 class="my-0"><strong>{{ trans('em.event_category') }}</strong></h6>
                                            <p class="mb-2">{{ event.title }} <small>({{ event.category_name }})</small></p>
                                        
                                            <div>
                                                <p><small><strong>{{ trans('em.venue') }} </strong></small></p>
                                                <p>
                                                    <small class="text-muted">{{ event.venue }}</small>
                                                    <small class="text-muted" v-if="event.online_location">( {{ trans('em.online') }} )</small>
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <h6 class="my-0"><strong>{{ trans('em.start') }} - {{ trans('em.end') }} {{ trans('em.date') }}</strong></h6>
                                            <p class="mb-2" v-if="event.repetitive > 0">
                                                {{ booking_date }} <small><strong>-</strong></small> <br>
                                                {{ booking_end_date != null ? booking_end_date : booking_date  }}
                                            </p>
                                             <p class="mb-2" v-else>
                                                {{ changeDateFormat(moment(userTimezone(event.start_date+' '+event.start_time, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD'))) }} <small><strong>-</strong></small> <br>
                                                {{ changeDateFormat(moment(userTimezone(event.end_date+' '+event.end_time,  'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD'))) }} 
                                            </p>
                                        
                                            <div>
                                                <p><small><strong>{{ trans('em.timings') }}</strong></small></p>
                                                <p v-if="event.repetitive > 0"><small class="text-muted">{{ start_time }} - {{ end_time }}  {{ '('+ showTimezone() +')'  }}</small></p>
                                                <p v-else>
                                                    <small class="text-muted">{{ changeTimeFormat(start_time) }} - {{ changeTimeFormat(end_time) }}
                                                        {{ '('+ showTimezone() +')'  }}
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                        
                                    </li>
                                </ul>
                                
                            </div>

                            <!-- Tickets -->
                            <div class="col-md-12">
                                <p class="mb-0 lead lead-caption text-center">{{ trans('em.tickets') }}</p>

                                <ul class="list-group m-0">
                                    
                                    <!-- <li class="list-group-item d-flex  justify-content-between lh-condensed d-flex-wrap"
                                        v-for="(item, index) in tickets" 
                                        :key = "index"
                                    > -->
                                    <!-- CUSTOM -->
                                    <li class="list-group-item  justify-content-between lh-condensed d-flex-wrap"
                                        v-for="(item, index) in tickets" 
                                        :key = "index"
                                    >
                                         
                                    
                                        <div class="row justify-content-around" v-if="item.sale_start_date != null">

                                            <div class="col-xs-3" 
                                                 v-if="
                                                userTimezone(item.sale_start_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') <= moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss') && 
                                                userTimezone(item.sale_end_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') > moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss')">
                                                <h4 class="title"> {{ trans('em.on_sale') }}
                                                </h4>
                                            </div>

                                            <div  class="col-xs-9 text-right" v-if="
                                            userTimezone(item.sale_start_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') <= moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss') && 
                                            userTimezone(item.sale_end_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') > moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss')">
                                                <p class="text-dark pt-2">
                                                    <i class='fas fa-clock fa-spin' style="color:red"></i>
                                                    <vue-countdown :time="timerOnSale(item.sale_start_date, item.sale_end_date)" v-slot="{ days, hours, minutes, seconds }">
                                                        {{ days }} {{ trans('em.days') }}, {{ hours }} : {{ minutes }} : {{ seconds }} {{ trans('em.left') }}
                                                    </vue-countdown>
                                                </p>
                                            </div>  
                                        </div>
                                        
                                        
                                        <ul class="list-group m-0 list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between lh-condensed  d-flex-wrap "
                                            >
                                        <!-- CUSTOM -->
                                                <input type="hidden" class="form-control" name="ticket_id[]" :value="item.id" >
                                                <input type="hidden" class="form-control" name="ticket_title[]" :value="item.title" >

                                                <div class="w-50">
                                                    <h6 class="my-0"><strong>{{ item.title }}</strong></h6>
                                                    <p class="mb-2">
                                                        
                                                         <!-- CUSTOM -->
                                                        <del v-if="item.sale_price != null && userTimezone(item.sale_start_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') <= moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss') && 
                                                                userTimezone(item.sale_end_date, 'YYYY-MM-DD HH:mm:ss').format('YYYY-MM-DD HH:mm:ss') > moment().tz(Intl.DateTimeFormat().resolvedOptions().timeZone).format('YYYY-MM-DD HH:mm:ss') 
                                                        ">
                                                            <span> {{ item.old_price > 0 ? item.old_price : '0.00' }}  </span>
                                                            <small>{{event.currency != null ? event.currency : currency }}</small>
                                                        </del>
                                                        <!-- CUSTOM -->
                                                        
                                                        {{ item.price > 0 ? item.price : '0.00' }} 
                                                        <!-- <small>{{currency}}</small> -->
                                                        <!-- CUSTOM -->
                                                        <small>{{event.currency != null ? event.currency : currency }}</small>
                                                        <!-- CUSTOM -->    
                                                    </p>
                                                    
                                                    <!-- show tax only if quantity is set -->
                                                    <!-- <div class="event-tax" v-if="quantity[index] > 0 && item.price > 0 && item.taxes.length > 0"> -->
                                                    <!-- CUSTOM -->
                                                    <div  class="event-tax" v-if="quantity[index] > 0 && item.price > 0 && item.taxes.length > 0 && item.is_donation <= 0">
                                                    <!-- CUSTOM -->
                                                        <div v-for="(tax, index1) in item.taxes" :key ="index1">
                                                            <p>{{ tax.title }} 
                                                                <small>{{ total_price[index] > 0 ? countTax(item.price, tax.rate, tax.rate_type, tax.net_price, quantity[index]) : 0 }}</small>
                                                            </p>
                                                        </div>
                                                    </div>

                                                            
                                                    <!-- CUSTOM -->
                                                    <div  class="event-tax" v-if="quantity[index] > 0 && item.price > 0 && item.taxes.length > 0 &&  item.is_donation > 0">
                                                        <div v-for="(tax, index1) in item.taxes" :key ="index1">
                                                            <p>{{ tax.title }} 
                                                                <small>{{ total_price[index] > 0 ? countTax(item.price, tax.rate, tax.rate_type, tax.net_price, 1) : 0 }}</small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- CUSTOM -->
                                                </div>
                                                
                                                <div  v-if="item.t_soldout > 0" class="w-10 w-20-mobile">
                                                    <p class="text-danger"><small><i class="fas fa-times-circle"></i> {{ trans('em.t_soldout') }}</small></p>
                                                </div>    

                                                <div  v-show="item.t_soldout <= 0" class="w-10 w-20-mobile">
                                                    <!-- CUSTOM -->
                                                    <div  v-if="item.seatchart != null && item.seatchart.status > 0 && is_bulk <= 0">
                                                        <p>{{ trans('em.quantity') }}: {{ quantity[index] }}</p>
                                                    </div>
                                                    
                                                    <!-- <div class="w-10 w-20-mobile"> -->
                                                    <!-- Hide quantity dropdown in case of reserved seating -->
                                                    <div  v-if="(item.seatchart == null || (item.seatchart != null  && item.seatchart.status <= 0) )  && is_bulk <= 0 " >
                                                    <!-- CUSTOM -->
                                                        
                                                        
                                                        <!-- Live stock alert  -->
                                                        <!-- if any booked tickets -->
                                                        <div v-if='typeof(booked_tickets[item.id+"-"+booked_date_server]) != "undefined"
                                                        '>
                                                            <select class="form-control form-input-sm" 
                                                                name="quantity[]" 
                                                                v-model="quantity[index]"
                                                                v-if='max_ticket_qty <= 100'
                                                            >
                                                                <option value="0" selected>0</option>
                                                                <option :key="ind"
                                                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant <= max_ticket_qty" 
                                                                    :value="itm" v-for=" (itm, ind) in booked_tickets[item.id+'-'+booked_date_server].total_vacant"  
                                                                >{{itm }}</option>
                                                                <option v-else :value="itm" v-for=" (itm, ind) in (item.quantity > max_ticket_qty ?  parseInt(max_ticket_qty) : parseInt(item.quantity))"  :key="ind">{{itm }}</option>
                                                            </select>
                                                            <input v-else type="number" name="quantity[]" 
                                                                v-model="quantity[index]" value="0" class="form-control form-input-sm" 
                                                                min="0" :max="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty ? booked_tickets[item.id+'-'+booked_date_server].total_vacant : max_ticket_qty">
                                                            <!-- Show if vacant less than max_ticket_qty -->
                                                            <p class="text-info" 
                                                                v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant > 0">
                                                                <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                {{ booked_tickets[item.id+'-'+booked_date_server].total_vacant }}</small>
                                                            </p>
                                                            <p class="text-danger" 
                                                                v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant <= 0">
                                                                <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                            </p>
                                                        </div>
                                                        <div v-else>
                                                            <select class="form-control form-input-sm" 
                                                                name="quantity[]" 
                                                                v-model="quantity[index]"
                                                                v-if="max_ticket_qty <= 100"
                                                            >
                                                                <option value="0" selected>0</option>
                                                                <option :value="itm" v-for=" (itm, ind) in item.quantity > max_ticket_qty ? parseInt(max_ticket_qty) : parseInt(item.quantity)"  :key="ind">{{itm }}</option>
                                                            </select>
                                                            <input v-else type="number" name="quantity[]" v-model="quantity[index]" value="0" class="form-control form-input-sm" min="0" :max="item.quantity > max_ticket_qty ?  parseInt(max_ticket_qty) : parseInt(item.quantity)" >
                                                            <!-- Show if vacant less than max_ticket_qty -->
                                                            <p class="text-info" 
                                                                v-if="item.quantity < max_ticket_qty && item.quantity > 0">
                                                                <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                {{ item.quantity }}</small>
                                                            </p>
                                                            <p class="text-danger" 
                                                                v-if="item.quantity <= 0">
                                                                <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <!-- CUSTOM -->
                                                    <!-- FOR BULK BOOKING -->
                                                    <div  v-else >
                                                        
                                                        <div v-if="is_bulk > 0 ">
                                                        <!-- Live stock alert  -->
                                                            <!-- if any booked tickets -->
                                                            <div v-if='typeof(booked_tickets[item.id+"-"+booked_date_server]) != "undefined"
                                                            '>
                                                            
                                                                <input  type="number" name="quantity[]" 
                                                                    v-model="quantity[index]"  value="0" class="form-control form-input-sm" 
                                                                    min="0" 
                                                                >
                                                                <!-- Show if vacant less than max_ticket_qty -->
                                                                <p class="text-info" 
                                                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant > 0">
                                                                    <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                    {{ booked_tickets[item.id+'-'+booked_date_server].total_vacant }}</small>
                                                                </p>
                                                                <p class="text-danger" 
                                                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant <= 0">
                                                                    <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                                </p>
                                                            </div>
                                                            <div v-else>
                                                                
                                                                <input  type="number" name="quantity[]" v-model="quantity[index]" value="0" class="form-control form-input-sm" min="0"  >
                                                                <!-- Show if vacant less than max_ticket_qty -->
                                                                <p class="text-info" 
                                                                    v-if="item.quantity < max_ticket_qty && item.quantity > 0">
                                                                    <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                    {{ item.quantity }}</small>
                                                                </p>
                                                                <p class="text-danger" 
                                                                    v-if="item.quantity <= 0">
                                                                    <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                                </p>
                                                            </div>
                                                        </div>


                                                        <div v-else class="w-10 w-20-mobile" :class="{'no-display': (item.seatchart != null && item.seatchart.status > 0) }"   >
                                                        
                                                            
                                                            
                                                            <!-- Live stock alert  -->
                                                            <!-- if any booked tickets -->
                                                            <div v-if='typeof(booked_tickets[item.id+"-"+booked_date_server]) != "undefined"
                                                            '>
                                                                <select class="form-control form-input-sm" 
                                                                    name="quantity[]" 
                                                                    v-model="quantity[index]"
                                                                    v-if='max_ticket_qty <= 100'
                                                                >
                                                                    <option value="0" selected>0</option>
                                                                    <option :key="ind"
                                                                        v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant <= max_ticket_qty" 
                                                                        :value="itm" v-for=" (itm, ind) in booked_tickets[item.id+'-'+booked_date_server].total_vacant"  
                                                                    >{{itm }}</option>
                                                                    <option v-else :value="itm" v-for=" (itm, ind) in (item.quantity > max_ticket_qty ?  parseInt(max_ticket_qty) : parseInt(item.quantity))"  :key="ind">{{itm }}</option>
                                                                </select>
                                                                <input v-else type="number" name="quantity[]" 
                                                                    v-model="quantity[index]" value="0" class="form-control form-input-sm" 
                                                                    min="0" :max="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty ? booked_tickets[item.id+'-'+booked_date_server].total_vacant : max_ticket_qty">
                                                                <!-- Show if vacant less than max_ticket_qty -->
                                                                <p class="text-info" 
                                                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant > 0">
                                                                    <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                    {{ booked_tickets[item.id+'-'+booked_date_server].total_vacant }}</small>
                                                                </p>
                                                                <p class="text-danger" 
                                                                    v-if="booked_tickets[item.id+'-'+booked_date_server].total_vacant < max_ticket_qty && booked_tickets[item.id+'-'+booked_date_server].total_vacant <= 0">
                                                                    <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                                </p>
                                                            </div>
                                                            <div v-else>
                                                                <select class="form-control form-input-sm" 
                                                                    name="quantity[]" 
                                                                    v-model="quantity[index]"
                                                                    v-if="max_ticket_qty <= 100"
                                                                >
                                                                    <option value="0" selected>0</option>
                                                                    <option :value="itm" v-for=" (itm, ind) in item.quantity > max_ticket_qty ?  parseInt(max_ticket_qty) : parseInt(item.quantity)"  :key="ind">{{itm }}</option>
                                                                </select>
                                                                <input v-else type="number" name="quantity[]" v-model="quantity[index]" value="0" class="form-control form-input-sm" min="0" :max="item.quantity > max_ticket_qty ?  parseInt(max_ticket_qty) : parseInt(item.quantity)" >
                                                                <!-- Show if vacant less than max_ticket_qty -->
                                                                <p class="text-info" 
                                                                    v-if="item.quantity < max_ticket_qty && item.quantity > 0">
                                                                    <small><i class="fas fa-exclamation"></i> {{ trans('em.vacant') }} 
                                                                    {{ item.quantity }}</small>
                                                                </p>
                                                                <p class="text-danger" 
                                                                    v-if="item.quantity <= 0">
                                                                    <small><i class="fas fa-times-circle"></i>  {{ trans('em.vacant') }} 0</small>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        
                                                    </div>
                                                </div>    
                                                            
                                                    <!-- CUSTOM -->
                                                    
                                                    <!-- <div class="w-30 text-right">
                                                        <strong>
                                                            {{ total_price[index] ? total_price[index] : '0.00' }}
                                                            <small>{{currency}}</small>
                                                        </strong>
                                                        <p v-if="quantity[index] > 0"><i class="fas fa-check-circle ticket-selected-text"></i></p>
                                                    </div> -->
                                                            
                                                            
                                                <!-- CUSTOM -->
                                                <div class="w-30 text-right" v-if="item.is_donation <= 0 && item.t_soldout <= 0">
                                                    <strong>
                                                        {{ total_price[index] ? total_price[index] : '0.00' }}
                                                        <small>{{currency}}</small>
                                                    </strong>
                                                    <p v-if="quantity[index] > 0"><i class="fas fa-check-circle ticket-selected-text"></i></p>
                                                </div>

                                                <div class="w-30 text-right" v-show="item.is_donation > 0 && quantity[index] > 0 && item.t_soldout <= 0">
                                                    <!-- <label class="custom-control-label" ><i class="fab fa-paypal"></i> {{ trans('em.is_do') }}</label> -->
                                                    <input type="text" name="is_donation[]" 
                                                            v-model="is_donation[index]" value="0" class="form-control form-input-sm" 
                                                            @keypress="NumbersOnly"
                                                            @change="isDonation"
                                                            :id="'donation_'+index"
                                                        
                                                        >

                                                        <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with button groups">
                                                            <div class="btn-group mr-1 mt-2" role="group" aria-label="First group">
                                                                <button type="button" class="btn btn-secondary" 
                                                                    @click="setDonationValue(index, 100)"
                                                                >$100.00</button>
                                                            
                                                            </div>
                                                            <div class="btn-group mr-1 mt-2" role="group" aria-label="Second group">
                                                                <button type="button" class="btn btn-secondary"
                                                                @click="setDonationValue(index, 500)"
                                                                >$500.00</button>
                                                        
                                                            </div>
                                                            <div class="btn-group mr-1 mt-2" role="group" aria-label="Third group">
                                                                <button type="button" class="btn btn-secondary"
                                                                @click="setDonationValue(index, 1000)"
                                                                >$1000.00</button>
                                                            </div>

                                                            <div class="btn-group mr-1 mt-2" role="group" aria-label="Third group">
                                                                <strong>
                                                                    {{ total_price[index] ? total_price[index] : '0.00' }}
                                                                    <small>{{currency}}</small>
                                                                </strong>
                                                                <p v-if="quantity[index] > 0"><i class="fas fa-check-circle ticket-selected-text"></i></p>
                                                            
                                                            </div>

                                                        </div>
                                                </div>

                                                <div class="w-30 text-right" v-show="item.is_donation > 0 && quantity[index] <= 0 && item.t_soldout <= 0">
                                                    
                                                    <p><i class="fas fa-check-circle ticket-selected-text"></i>{{ trans('em.is_donation') }}</p>
                                                    
                                                </div>
                                                <!-- CUSTOM -->

                                                <div class="break-flex">
                                                    <!-- hide/show below ticket description -->
                                                    <a class="pointer ticket-info-toggle" @click="ticket_info = !ticket_info">
                                                        <small v-if="ticket_info">{{ trans('em.hide_info') }}</small>
                                                        <small v-else>{{ trans('em.show_info') }}</small>
                                                    </a>
                                                    <p class="ticket-info" v-if="ticket_info">{{ item.description }}</p>
                                                </div>

                                                <!-- *** CUSTOM *** start-->
                                                
                                                <div style="overflow: auto;" class="break-flex" v-if="item.seatchart != null && item.seatchart.status > 0 && is_bulk <= 0 && item.t_soldout <= 0">
                                                    <seat-component :ticket="item" :ticket_index="index" :max_ticket_qty="max_ticket_qty" :quantity="quantity[index]" :event="event"></seat-component> 
                                                </div>

                                                <div class="break-flex" v-show="item.promocodes.length > 0">
                                                    <div class="input-group mt-5" v-show="item.price > 0 && item.t_soldout <= 0 && is_bulk <= 0">
                                                        <input type="text" name="promocode[]" class="form-control form-input-sm" :placeholder="trans('em.enter_promocode')" 
                                                            v-model="promocode[index]"
                                                            :id="'pc_'+index"
                                                        >

                                                        <span class="input-group-btn">
                                                            <button class="btn lgx-btn lgx-btn-sm lgx-btn-success" type="button" 
                                                                @click="pc_readonly[index] > 0 ? '' : applyPromocode(item.id, index)"
                                                                :id="'pcb_'+index"
                                                            >
                                                                    {{ trans('em.apply') }}
                                                            </button>
                                                        </span>
                                                    </div>

                                                    <span class="text-success" :id="'pc_success_'+index"></span>
                                                    <span class="text-danger" :id="'pc_error_'+index"></span>
                                                </div>
                                                
                                                <div class="break-flex" v-if="parseInt(quantity[index]) > 0 && is_bulk <= 0"  v-for=" (q_num, q_index) in parseInt(quantity[index])"  :key="q_index">
                                                    <attendee-component ref="attendee" v-if="parseInt(q_num) > 0" :ticket_index="index" :quantity_index="q_index" ></attendee-component>
                                                </div>

                                                
                                                <!-- *** CUSTOM *** end-->
                                       <!-- CUSTOM -->
                                            </li>
                                        </ul>        
                                        <!-- CUSTOM -->    
                                    </li>
                                </ul>
                            </div>

                            <!-- Cart Totals -->
                            <div class="col-md-12">
                                <p class="m-0 lead lead-caption text-center">{{ trans('em.cart') }}</p>

                                <ul class="list-group m-0">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <h6 class="my-0"><strong>{{ trans('em.total_tickets') }}</strong></h6>
                                        <strong :class="{'ticket-selected-text': bookedTicketsTotal() > 0 }">{{ bookedTicketsTotal() }}</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <h6 class="my-0"><strong>{{ trans('em.total_order') }}</strong></h6>
                                        <strong :class="{'ticket-selected-text': bookedTicketsTotal() > 0 }">{{ total }} <small>{{currency}}</small></strong>
                                    </li>    

                                    <!-- *** CUSTOM *** -->
                                    <li class="list-group-item" v-if="total > 0 && promocode_reward > 0">
                                        <span class="badge bg-green"><strong>{{'-' + promocode_reward.toFixed(2)}} {{currency}}</strong></span>
                                        <h4>{{ trans('em.rewards') }}</h4>
                                    </li>
                                    <li class="list-group-item" v-if="total > 0 && promocode_reward > 0">
                                        <span class="badge bg-green"><strong>{{ (parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) }} {{currency}}</strong></span>
                                        <h4>{{ trans('em.net_order_total') }}</h4>
                                    </li>
                                    <!-- *** CUSTOM *** -->  

                                </ul>
                            </div>

                            <!-- If not logged in -->
                            <!-- <div class="col-md-12" v-if="!login_user_id"> -->
                            <!-- CUSTOM -->
                            <!-- <div class="col-md-12" v-if="!register_user_id">
                                <div class="alert alert-danger">
                                    {{ trans('em.please_login_signup') }}
                                </div>
                            </div> -->

                            <!-- Payments -->
                            <!-- <div class="col-md-12" v-if="bookedTicketsTotal() > 0 && login_user_id"> -->
                            <!-- CUSTOM -->
                            <div class="col-md-12" v-if="bookedTicketsTotal() > 0 ">
                            <!-- CUSTOM -->    
                                <p class="m-0 lead lead-caption text-center ">{{ trans('em.payment') }}</p>

                                <!-- Free -->
                                <!-- <div class="d-block my-3 pl-3" v-if="total <= 0"> -->
                                <div class="d-block my-3 pl-3" v-if="(parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) <= 0">
                                    <div class="radio-inline">
                                        <input id="free_order" name="free_order" type="radio" class="custom-control-input" checked v-model="payment_method" value="free">
                                        <label class="custom-control-label" for="free_order"> &nbsp;<i class="fas fa-glass-cheers"></i> {{ trans('em.free') }} <small>({{ trans('em.rsvp') }} )</small></label>
                                    </div>
                                </div>

                                <!-- Paid -->
                                <div class="d-block my-3 pl-3" v-else-if="(parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) > 0">
                                    
                                    <!-- For Organizer & Customer -->
                                    <div class="radio-inline" v-if="is_admin <= 0 && is_paypal > 0 && is_bulk <= 0">
                                        <input type="radio" class="custom-control-input" id="payment_method_paypal" name="payment_method" v-model="payment_method" value="1" >
                                        <label class="custom-control-label" for="payment_method_paypal"> &nbsp;<i class="fab fa-paypal"></i> {{ trans('em.paypal') }}</label>
                                    </div>

                                    <!--  CUSTOM -->
                                    <!-- For Organizer & Customer stripe-->
                                    <div class="radio-inline" v-if="is_admin <= 0 && isStripe > 0 && is_bulk <= 0 && is_stripe_direct <= 0" >
                                        <input type="radio" class="custom-control-input" id="payment_method_stripe" name="payment_method" v-model="payment_method" value="2" >
                                        <label class="custom-control-label" for="payment_method_stripe"> &nbsp;<i class="fab fa-stripe"></i> {{ trans('em.card_payment') }}</label>
                                    </div>

                                    
                                    <div class="radio-inline" v-if="is_admin <= 0 && isAuthorizeNet > 0 && is_bulk <= 0">
                                        <input type="radio" class="custom-control-input" id="payment_method_authorize" name="payment_method" v-model="payment_method" value="3" >
                                        <label class="custom-control-label" for="payment_method_authorize"> {{ trans('em.authorizenet') }}</label>
                                    </div>

                                    
                                    
                                    <div class="radio-inline" v-if="is_admin <= 0 && is_bitpay > 0 && is_bulk <= 0">
                                        <input type="radio" class="custom-control-input" id="payment_method_bitpay" name="payment_method" v-model="payment_method" value="4" >
                                        <label class="custom-control-label" for="payment_method_bitpay"> {{ trans('em.bitpay') }}</label>
                                    </div>

                                    <div class="radio-inline" v-if="is_admin <= 0 && is_stripe_direct > 0 && is_bulk <= 0">
                                        <input type="radio" class="custom-control-input" id="payment_method_stripe_direct" name="payment_method" v-model="payment_method" value="5" >
                                        <label class="custom-control-label" for="payment_method_stripe_direct"> &nbsp;<i class="fab fa-stripe"></i> {{ trans('em.card_payment') }}</label>
                                    </div>

                                    <div class="radio-inline" v-if="is_admin <= 0 && is_bulk <= 0 && is_pay_stack > 0">
                                        <input type="radio" class="custom-control-input" id="payment_method_paystack" name="payment_method" v-model="payment_method" value="6" >
                                        <label class="custom-control-label" for="payment_method_paystack">PayStack</label>
                                    </div>
                                    
                                    <!-- CUSTOM -->

                                    <!-- For Admin & Organizer & Customer -->
                                    <div class="radio-inline" 
                                        v-if="(is_organiser > 0 && is_offline_payment_organizer > 0) || (is_customer > 0 && is_offline_payment_customer > 0) || (is_admin > 0) || (is_bulk > 0)">
                                        
                                        <input type="radio" class="custom-control-input" id="payment_method_offline" name="payment_method" v-model="payment_method" value="offline">

                                        <label class="custom-control-label" for="payment_method_offline"> 
                                            &nbsp;<i class="fas fa-suitcase-rolling"></i> {{ trans('em.offline') }} 
                                            <small>({{ trans('em.cash_on_arrival') }})</small>
                                        </label>

                                    </div>

                                    <p v-if="payment_method == 'offline'" class="text-info"><strong>{{ trans('em.offline_payment_info') }}: </strong><small v-html="event.offline_payment_info"></small></p>

                                    <p class="text-mute mt-5" v-html="trans('em.order_terms')"></p>
                                    
                                </div>
                                
                            </div>

                        </div>
                        
                         <!--  CUSTOM -->
                        <stripe-component 
                            ref="stripe" 
                            v-if="payment_method == 2 && total > 0 && register_user_id  && is_admin <= 0 && isStripe > 0 && (parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) > 0"
                            :payment_method="payment_method"   :bookings_data="bookings_data" 
                            :stripePublishableKey="stripePublishableKey"
                            :stripeSecretKey="stripe_secret_key"
                        ></stripe-component>

                        <authorize-net v-if="payment_method == 3 && total > 0 && register_user_id  && is_admin <= 0 && isAuthorizeNet > 0 (parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) > 0"></authorize-net>
                        
                        <pay-stack ref="pay_stack" v-if="payment_method == 6 && total > 0 && register_user_id && is_admin <= 0 && is_pay_stack > 0 && (parseFloat(total) - parseFloat(promocode_reward)).toFixed(2) > 0"></pay-stack>

                        <!--  CUSTOM -->

                        <div class="row mt-1">
                            <!-- <div class="col-xs-12" v-if="login_user_id"> -->
                            <!-- CUSTOM -->
                            <div class="col-xs-12" v-if="register_user_id">
                            <!-- CUSTOM -->
                                <button :class="{ 'disabled' : disable }"  :disabled="disable" type="submit" class="btn lgx-btn btn-block" ><i class="fas fa-cash-register"></i> {{ (total <= 0) ? trans('em.rsvp') : trans('em.checkout') }}</button>
                            </div>
                            <!-- <div class="col-xs-12" v-else> -->
                             <!-- CUSTOM -->
                            <div class="col-xs-12" v-if="!register_user_id">
                            <!-- CUSTOM -->
                                <div class="btn-group btn-group-justified">
                                    <button type="button" class="btn lgx-btn w-50" @click="signupFirst()"><i class="fas fa-user-plus"></i> {{ trans('em.register') }}</button>
                                    <button type="button" class="btn lgx-btn lgx-btn-black w-50" @click="register_modal = 1"><i class="fas fa-fingerprint"></i> {{ trans('em.continue_guest') }}</button>
                                </div>
                                <register-user v-if="register_modal > 0" :register_user_id="register_user_id" :register_modal="register_modal" ></register-user>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>

    </div>
</template>

<script>

import { mapState, mapMutations} from 'vuex';
import mixinsFilters from '../../../../mixins.js';
import _ from 'lodash';
//   CUSTOM 
import Stripe from './custom/Stripe.vue';
import RegisterUser   from './custom/RegisterUser.vue';
import AuthorizeNet from './custom/AuthorizeNet.vue';
import AddAttendee   from './custom/AddAttendee';
import VueCountdown from '@chenfengyuan/vue-countdown';
import AttendeeComponent from './custom/Attendee.vue';
import SeatComponent from './custom/Seat';
import PayStack from './custom/PayStack';
//  CUSTOM 

export default {
    
    components: {
        //  CUSTOM
        'stripe-component' : Stripe,
        RegisterUser,
        AuthorizeNet,
        AddAttendee,
        VueCountdown,
        AttendeeComponent,
        SeatComponent,
        'pay-stack'        : PayStack,
        // CUSTOM 
    },

    mixins:[
        mixinsFilters
    ],

    props : [
        'tickets', 
        'max_ticket_qty', 
        'event', 
        'currency', 
        'login_user_id',
        'is_admin',
        'is_organiser',
        'is_pos',
        'is_customer',
        'is_paypal',
        'is_offline_payment_organizer',
        'is_offline_payment_customer',
        'booked_tickets',
    ],
    data() {
        return {
            openModal           : false,
            ticket_info         : false,    
            moment              : moment,
            quantity            : [1],
            price               : null,
            total_price         : [],
            customer_id         : 0,
            total               : 0,   
            disable             : false,
            payment_method      : 'offline',   
            
            // customers options
            options             : [],
            //selected customer
            customer            : null,

            /* CUSTOM */
            //promocode
            bookings_data         : null,
            stripePublishableKey  : stripe_publishable_key,
            stripe_secret_key     : stripe_secret_key,
            isStripe              : is_stripe,

            name                  : '',
            email                 : '',
            register_user_id      : this.login_user_id,
            register_modal        : 0,   

               //promocode
            promocode           : [],
            ticket_promocodes   : [],
            pc_readonly         : [],
            promocode_reward    : 0,
            
            cardName    : "",
            cardNumber  : "",
            cardMonth   : "",
            cardYear    : "",
            cardCvv     : "",
            isAuthorizeNet   : is_authorize_net,
            is_bitpay        : is_bitpay,
            is_stripe_direct : is_stripe_direct,
            add_attendee        : 0,
            is_twilio           : is_twilio,
            phone_t               : '',

            name                : [[],[]],
            phone               : [[],[]],
            address             : [[],[]],
            is_bulk             : 0,

            is_donation         : [],
            pay_stack           : 0,
            is_pay_stack        : is_pay_stack

            /* CUSTOM */
        }
    },

     computed: {
        // get global variables
        ...mapState( ['booking_date', 'start_time', 'end_time', 'booking_end_date', 'booked_date_server']),
    },
   
    methods: {
        // update global variables
        ...mapMutations(['add', 'update']),

        // reset form and close modal
        close: function () {    
            this.price          = null;
            this.quantity       = [];
            this.total_price    = [];
            
            this.add({
                booking_date        : null,
                booked_date_server  : null,
                booking_end_date    : null,
                start_time          : null,
                end_time            : null,
            })
            
            
            this.openModal      = false;
        },

        bookTickets(){
            // show loader
            this.showLoaderNotification(trans('em.processing'));

            // prepare form data for post request
            this.disable = true;

            let post_url = route('eventmie.bookings_book_tickets');
            let post_data = new FormData(this.$refs.form);

            
            //CUSTOM
            post_data.append('name', JSON.stringify(this.name) );
            post_data.append('phone', JSON.stringify(this.phone) );
            post_data.append('address', JSON.stringify(this.address) );
            //CUSTOM
            
            /* CUSTOM */
            // in case of Stripe    
            if(this.payment_method == 2 && this.total > 0) {
                this.stripePayment(post_data);
            } else if(this.payment_method == 6 && this.total > 0){
                this.PayStack(post_data);
            } else {
            /* CUSTOM */
                // axios post request
                axios.post(post_url, post_data)
                .then(res => {
                    
                    if(res.data.status && res.data.message != ''  && typeof(res.data.message) != "undefined") {
                    
                        // hide loader
                        Swal.hideLoading();

                        // close popup
                        this.close();
                        this.showNotification('success', res.data.message);
                        
                    }
                    else if(!res.data.status && res.data.message != '' && res.data.url != ''  && typeof(res.data.url) != "undefined"){
                        
                        // hide loader
                        Swal.hideLoading();
                        
                        // close popup
                        this.close();
                        this.showNotification('error', res.data.message);
                        
                        setTimeout(() => {
                            window.location.href = res.data.url;    
                        }, 1000);
                    }

                    if(res.data.url != '' && res.data.status  && typeof(res.data.url) != "undefined") {
                        
                        // hide loader
                        Swal.hideLoading();

                        setTimeout(() => {
                            window.location.href = res.data.url;    
                        }, 1000);
                    }

                    if(!res.data.status && res.data.message != ''  && typeof(res.data.message) != "undefined") {
                    
                        // hide loader
                        Swal.hideLoading();

                        // close popup
                        this.close();
                        this.showNotification('error', res.data.message);
                        
                    }

                })
                .catch(error => {
                    this.disable = false;
                    let serrors = Vue.helpers.axiosErrors(error);
                    if (serrors.length) {
                        
                        this.serverValidate(serrors);
                        
                    }
                });
            }
        },
        
        

        // validate data on form submit
        validateForm(e) {
            this.$validator.validateAll().then((result) => {
                if (result) {
                    this.disable = true;
                    // this.formSubmit(e);
                    //CUSTOM
                    this.bookTickets();
                    //CUSTOM
                }
                else{
                    this.disable = false;
                }
            });
        },

        // show server validation errors
        serverValidate(serrors) {
            this.disable = false;
            this.$validator.validateAll().then((result) => {
                this.$validator.errors.add(serrors);
            });
        },

        
        // count total tax
        countTax(price, tax, rate_type, net_price, quantity) {
            
            price           = parseFloat(price).toFixed(2);
            tax             = parseFloat(tax).toFixed(2);
            var total_tax   = parseFloat(quantity * tax).toFixed(2);
            
            
                // in case of percentage
                if(rate_type == 'percent')
                {
                    if(isNaN((price * total_tax)/100))
                        return 0;
                    
                    total_tax = (parseFloat((price*total_tax)/100)).toFixed(2); 

                    if(net_price == 'excluding')
                        return total_tax+' '+this.currency+' ('+tax+'%'+' '+trans('em.exclusive')+')';
                    else
                        return total_tax+' '+this.currency+' ('+tax+'%'+' '+trans('em.inclusive')+')';
                }    

                // for fixed tax
                if(rate_type == 'fixed')
                {
                    if(net_price == 'excluding')
                        return total_tax+' '+this.currency+' ('+tax+' '+this.currency+' '+trans('em.exclusive')+')';
                    else
                        return total_tax+' '+this.currency+' ('+tax+' '+this.currency+' '+trans('em.inclusive')+')';
                }        
                
            return 0;        
        },

        // count total price
        totalPrice(){
            if(this.quantity != null || this.quantity.length > 0)
            {
                let amount;
                let tax;
                let total_tax ;
                this.quantity.forEach(function(value, key) {
                    total_tax               = 0;
                    this.total_price[key]   = [];

                    //CUSTOM
                    if(this.tickets[key].is_donation <= 0){
                        
                        amount                  = (parseFloat(value * this.tickets[key].price)).toFixed(2);
                    }else if( value > 0 && this.tickets[key].is_donation > 0){
                        
                        amount                  = (parseFloat(1 * this.tickets[key].price)).toFixed(2);
                    }else if(value <= 0 && this.tickets[key].is_donation > 0){
                        
                        this.$parent.tickets[key].price = 0;
                        this.is_donation[key] = 0;
                        amount = 0;
                    }
                    //CUSTOM
                    
                    // amount                  = (parseFloat(value * this.tickets[key].price)).toFixed(2);

                    // when have no taxes set set total_price with actual ammount without taxes
                    if(Object.keys(this.total_price).length > 0)
                    {
                        this.total_price.forEach(function(v, k){

                            // if(Object.keys(v).length <= 0);
                            //CUSTOM
                            if(Object.keys(v).length <= 0 && !isNaN(v));
                            //CUSTOM
                                this.total_price[key] = amount;
                                
                        }.bind(this))
                    }
                    if(this.tickets[key].taxes.length > 0 && amount > 0) {

                        this.tickets[key].taxes.forEach(function(tax_v, tax_k) {
                                    // in case of percentage
                            if(tax_v.rate_type == 'percent')
                            {
                                // in case of excluding
                                if(tax_v.net_price == 'excluding')
                                {
                                    tax = isNaN((amount * tax_v.rate)/100) ? 0 : (parseFloat((amount*tax_v.rate)/100)).toFixed(2);
                                
                                    total_tax   =  parseFloat(total_tax) + parseFloat(tax);
                                }
                            }

                            // // in case of percentage
                            if(tax_v.rate_type == 'fixed')
                            {
                                tax   = parseFloat(value *tax_v.rate);
                                
                                // // in case of excluding
                                if(tax_v.net_price == 'excluding')
                                    total_tax   = parseFloat(total_tax) + parseFloat(tax);

                            }
                            
                        }.bind(this))    
                    }
                    
                    this.total_price[key] = (parseFloat(amount) + parseFloat(total_tax)).toFixed(2);
                    
                }.bind(this));
            }
        },

        updateItem() {
            this.$emit('changeItem');
        },

        setDefaultQuantity() {
            // only set default value once
            var _this   = this;
            var promise = new Promise(function(resolve, reject) { 
                // only set default value once
                if(_this.quantity.length == 1) {
                    _this.tickets.forEach(function(value, key) {
                        if(key == 0)
                            _this.quantity[key] = 0;
                        else
                            _this.quantity[key] = 0;
                            
                    }.bind());
                }
                resolve(true);
            }); 

            promise.then(function(successMessage) {
                _this.totalPrice();
                _this.orderTotal();
                  /* CUSTOM start*/
                _this.resetPromocode();
                /* CUSTOM end*/
            }, function(errorMessage) { 
                
            }); 
        },

        // count prise all booked tickets
        orderTotal() {
            this.total = 0
            if(Object.keys(this.total_price).length > 0)
            {
                this.total_price.forEach(function(value, key){
                    
                    
                    //CUSTOM
                    if(isNaN(value))
                        value = 0;
                    //CUSTOM    

                    this.total = (parseFloat(this.total) + parseFloat(value)).toFixed(2);
                    
                }.bind(this))

                return this.total;
            }
            return 0;    
        },

        // total booked tickets
        bookedTicketsTotal() {
            let  total = 0
            if(this.quantity.length > 0)
            {
                this.quantity.forEach(function(value, key){
                    total = parseInt(total) + parseInt(value);

                }.bind(this))

                return total;
            }
            return 0;    
        },

        defaultPaymentMethod() {
            // if not admin
            // total > 0
            // if(this.is_admin <= 0 && this.bookedTicketsTotal() > 0)
            //     this.payment_method = 1;

               //CUSTOM
            // if premium order & not-admin
            if(this.is_admin <= 0 && this.orderTotal() > 0 && (parseFloat(this.total) - parseFloat(this.promocode_reward)).toFixed(2) > 0)
                this.payment_method = default_payment_method; 
            // if premium order & admin
            else if(this.is_admin >= 1 && this.orderTotal() > 0 && (parseFloat(this.total) - parseFloat(this.promocode_reward)).toFixed(2) > 0)
                this.payment_method = 'offline'; 
            else
                this.payment_method = default_payment_method;

            if(this.is_bulk > 0)
            {
                this.payment_method = 'offline';
            }

            // for free
            if((parseFloat(this.total) - parseFloat(this.promocode_reward)).toFixed(2) <= 0)
                this.payment_method = 'free';

            
            //CUSTOM
        },

        loginFirst() {
            window.location.href = route('eventmie.login_first');
        },
        signupFirst() {
            window.location.href = route('eventmie.signup_first');
        },

        // get customers

        getCustomers(loading, search = null){
            var postUrl     = route('eventmie.get_customers');
            var _this       = this;
            axios.post(postUrl,{
                'search' :search,
            }).then(res => {
                
                var promise = new Promise(function(resolve, reject) { 
                    
                    _this.options = res.data.customers;
                    
                    resolve(true);
                }) 
                
                promise 
                    .then(function(successMessage) { 
                        loading(false);
                    }, function(errorMessage) { 
                    //error handler function is invoked 
                        console.log(errorMessage); 
                    }) 
            })
            .catch(error => {
                let serrors = Vue.helpers.axiosErrors(error);
                if (serrors.length) {
                    this.serverValidate(serrors);
                }
            });
        },
        
        // v-select methods
        onSearch(search, loading) {
            loading(true);
            this.search(loading, search, this);
        },

        // v-select methods
        search: _.debounce((loading, search, vm) => {
            
            if(vm.validateEmail(search))
                vm.getCustomers(loading, search);
            else
                loading(false);    
            
        }, 350),

        validateEmail(email) {
            const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email);
        },

        //CUSTOM
        // stipe open modal
        stripePayment(bookings_data = null) {
           this.bookings_data = bookings_data;
           this.$refs.stripe.stripeCheckout();
           
        },

        //apply promocode
        applyPromocode(ticket_id =  null, index = null){
            
            if(this.register_user_id == null){
                this.showNotification('error', trans('em.please_login'));
                return true;
            }    

            if((this.is_admin > 0 || this.is_organiser > 0) && this.customer == null){
                this.showNotification('error', trans('em.select_customer'));
                return true;
            }
            

            if(ticket_id != null && this.promocode[index] != null && this.promocode[index] != '' && this.promocode[index] != 'undefined'){
                axios.post(route('apply_promocodes'),{
                    'ticket_id' : ticket_id,
                    'promocode' : this.promocode[index],
                    'customer_id' : (this.is_customer > 0) ? this.register_user_id : this.customer_id,
                })
                .then(res => {
                    
                    if(res.data.status > 0) {
                        console.log('success');
                        
                        var _this   = this;
                        var promise = new Promise(function(resolve, reject) { 
                            _this.ticket_promocodes[index] = res.data.promocode;
                            resolve(true);
                        });     
                            
                        promise 
                        .then(function(successMessage) { 
                            _this.promocodeReward();
                        }, function(errorMessage) { 
                            console.log(errorMessage); 
                        }); 
                        
                        // success
                        this.pc_readonly[index]  = 1;
                        
                        // promocode apply button text change
                        document.getElementById('pcb_'+index).innerHTML = trans('em.applied');

                        // error field set null
                        document.getElementById('pc_error_'+index).innerHTML = '';

                        // show promocode's reward
                        document.getElementById('pc_success_'+index).innerHTML = res.data.promocode.reward+
                        (res.data.promocode.p_type == 'fixed' ? this.currency : '%')+' OFF!';

                        // promocode input field readonly 
                        document.getElementById('pc_'+index).readOnly = true; 
                        
                        // promocode apply button disable
                        document.getElementById('pcb_'+index).disabled = true; 

                    } else {
                        console.log('error');
                        // error
                        this.pc_readonly[index]  = 0;

                        // promocode input field readonly 
                        document.getElementById('pc_'+index).readOnly = false; 
                        
                        // error field set 
                        document.getElementById('pc_error_'+index).innerHTML   = res.data.message;

                        //success message set null
                        document.getElementById('pc_success_'+index).innerHTML = '';

                        // promocode input field clear 
                        document.getElementById('pc_'+index).value = ''; 

                        // promocode v-model value set null    
                        this.promocode[index] = '';
                    }

                    
                })
                .catch(error => {
                    Vue.helpers.axiosErrors(error);
                });   
            }
        },

        // promocode' reward 
        promocodeReward(){
            
            this.promocode_reward = 0;

            if(Object.keys(this.ticket_promocodes).length > 0){
            
                this.ticket_promocodes.forEach(function(value, key){
                    if(value != 'undefined' && value != '' && value != null) {

                        if(value.p_type == 'fixed'){
                            
                            this.promocode_reward =  isNaN(parseFloat(this.promocode_reward) + parseFloat(value.reward)) ? this.promocode_reward :
                                                     (parseFloat(this.promocode_reward) + parseFloat(value.reward)); 
                        }
                        else {
                            
                            if(this.total_price[key] > 0) {
                                this.promocode_reward = this.promocode_reward + (isNaN((this.total_price[key]*value.reward)/100) ? this.promocode_reward : parseFloat((this.total_price[key]*value.reward)/100)); 
                            }
                        }  
                    }
                    
                }.bind(this))
            }
            this.defaultPaymentMethod();
            return this.promocode_reward.toFixed(2);
        },

        // reset promocode fields
        resetPromocode(){   
            
            this.quantity.forEach(function(value, key){
                
                if(Number(value) <= 0 || value == '' || value == 'undefined'  || value == null  ){
                    
                    // promocode apply button disable
                    document.getElementById('pcb_'+key).disabled = false; 

                    // promocode input field readonly 
                    document.getElementById('pc_'+key).readOnly = false; 

                    // success
                    this.pc_readonly[key]  = 1;

                    //clear field
                    this.promocode[key] = '';
                    document.getElementById('pc_'+key).value = '';
                    
                    // promocode apply button text change
                    document.getElementById('pcb_'+key).innerHTML = trans('em.apply');
                   
                    // error field set null
                    document.getElementById('pc_error_'+key).innerHTML = '';

                    // show promocode's reward
                    document.getElementById('pc_success_'+key).innerHTML = '';
                    
                    // promocode input field readonly 
                    document.getElementById('pc_'+key).readOnly = true; 

                    // promocode apply button disable
                    document.getElementById('pcb_'+key).disabled = true; 
                    
                    this.ticket_promocodes[key] = '';
                }
                else{
                  
                    if(this.promocode[key] == 'undefined' || this.promocode[key] == '') {

                        // promocode apply button disable
                        document.getElementById('pcb_'+key).disabled = false; 

                        // promocode input field readonly 
                        document.getElementById('pc_'+key).readOnly = false; 

                        this.pc_readonly[key]  = 0;
                        
                        document.getElementById('pc_'+key).value = '';
                        
                        // promocode apply button text change
                        document.getElementById('pcb_'+key).innerHTML = trans('em.apply');

                        // error field set null
                        document.getElementById('pc_error_'+key).innerHTML = '';

                        // show promocode's reward
                        document.getElementById('pc_success_'+key).innerHTML = '';
                    }


                }
                
            }.bind(this))   
        },

        timerOnSale(sale_start_date = null, sale_end_date = null){

            if(sale_start_date == null || sale_end_date == null)
                return true;
            
            var local_tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            
            var a    = this.userTimezone(sale_end_date, 'YYYY-MM-DD HH:mm:ss').format('DD/MM/YYYY HH:mm:ss');
            var b    = moment().tz(local_tz).format('DD/MM/YYYY HH:mm:ss');
            var ms   = 0; // milliseconds
            
            if(moment(a,"DD/MM/YYYY HH:mm:ss") > moment(b,"DD/MM/YYYY HH:mm:ss")){
                ms = moment(a,"DD/MM/YYYY HH:mm:ss").tz(local_tz).diff(moment(b,"DD/MM/YYYY HH:mm:ss").tz(local_tz)); //milliseconds
                
            }
           
            return ms;
        },

        // create attenddes fields
        createFields(){

            this.tickets.forEach(function(v, k){
                this.name[k]    = [];
                this.phone[k]   = [];
                this.address[k] = [];
            }.bind(this))
        },

        // for input box
        isDonation(){
            
            if(this.tickets.length > 0)
            {
                this.tickets.forEach(function(value, key){
                    if(value.is_donation > 0 ){
                        
                        
                        if(this.is_donation[key] == undefined || this.is_donation[key] == ''){
                            this.is_donation[key] = parseFloat(0);
                            this.total_price[key] = parseFloat(0);   
                        }    

                        this.$parent.tickets[key].price =  parseFloat(this.is_donation[key]).toFixed(2);
                        
                        this.is_donation[key] = this.is_donation[key];
           
                    }

                }.bind(this))   
            }
        },

        // for group button
        setDonationValue(index = null, price = 0){

            if(index == null)
                return true;
            
            if(this.tickets[index].is_donation > 0){
                    
                var id                             = 'donation_'+index;
                document.getElementById(id).value  = parseFloat(price);
                this.is_donation[index]            = parseFloat(price);
                this.tickets[index].price          = parseFloat(price);
                this.quantity[index]               = this.quantity[index];
                   
                this.isDonation();
                this.totalPrice();
                this.orderTotal();
                this.$forceUpdate();
            }
            
        },

        
        // accept only numeric value
        NumbersOnly(evt) {
            var evt = (evt) ? evt : window.event;
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if ((charCode > 31 && (charCode < 48 || charCode > 57)) && charCode !== 46) {
                evt.preventDefault();;
            } else {
                return true;
            }
        },

                        //paystack payment
        PayStack(booking_data){
            this.$refs.pay_stack.PayStack(booking_data)
        }



        //CUSTOM

    },

    watch: {
        quantity: function () {
            //CUSTOM
            this.isDonation();
            //CUSTOM

            this.totalPrice();
            this.orderTotal();
            this.defaultPaymentMethod();

            /* CUSTOM */
            this.resetPromocode();
            this.promocodeReward();
            
            /* CUSTOM */
        },
        tickets: function() {
            //CUSTOM
            this.isDonation();
            //CUSTOM
            
            this.totalPrice();
            this.orderTotal();
        },

        // active when customer search 
        customer: function () {
            this.customer_id = this.customer != null ?  this.customer.id : null;
        },

        
        // CUSTOM
        is_donation: function() {
            this.isDonation();
            this.totalPrice();
            this.orderTotal();
        },

        // CUSTOM
    
    },

    mounted() {
        this.openModal = true;
        this.setDefaultQuantity();
        this.defaultPaymentMethod();
         //  CUSTOM
        this.createFields();
        //  CUSTOM 
    },
}
</script>