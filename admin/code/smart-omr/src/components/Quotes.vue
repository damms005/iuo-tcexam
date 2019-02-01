<template>
  <div class="absolute flex quotes-main-component w-full h-full">

    <transition
      enter-active-class="animated fadeIn fast"
      leave-active-class="animated fadeOut faster"
      mode="out-in"
    >
      <div class="absolute inline-block h-48 mt-2 w-full" v-if="this.show_quote">

        <blockquote class="flex flex-col justify-center">

          <div class="quotation flex-1 tracking-wide text-center">
            <span class="pl-6 font-sans text-sm font-hairline">
              {{quote.quote}}
            </span>
          </div>
          
          <div class="self-center block pin-r mr-2 mt-6 leading-loose">
            <div class="relative flex">
              <div class="mt-1 pin-y inline absolute opacity-50 p-1 w-6 h-6" id="progressbar"></div>
              <div class="inline ml-8 pin-y author italic text-xs font-bold tracking-wide">
                <span class="font-heavy text-heavy">&dash;</span>
                <span>
                  {{quote.author}}
                </span>
              </div>
              <!-- <small>{{quote.genre}}</small> -->
            </div>
          </div>
        </blockquote>

      </div>

    </transition>
  </div>
</template>

<style type="text/css">
  .v-enter-active, .v-leave-active {
    transition: opacity .5s;
  }
  .v-enter, .v-leave-to /* .v-leave-active below version 2.1.8 */ {
    opacity: 0;
  }
</style>

<script>

  import ProgressBar from 'progressbar.js'

   //get quotes from the database
   //we will be getting 100 quotes randomly from the db
   //we display  a quote every 7 secs
   //at each display iteration, if quotes available is less than 20, we fetch another 100
export default {  
  props: [
      "webserver_url"
  ],
  data:function(){
    return {
      available_quotes:[],
      quote: '',
      quotes_change_interval: 7000,//in ms
      show_quote: true,
      quotesBarWidth: 0,
      count: 0,
      ongoingTimeout: 0,
      progressbar: null
    }
  },
  computed: {
  },
  mounted:function(){
    this.progressbar = new ProgressBar.Circle("#progressbar", {
          strokeWidth: 9,
          easing: 'linear',
          duration: 1000,
          color: 'grey',
          trailColor: '',
          trailWidth: 0,
          svgStyle: null,
          fill: null,
          warnings: true
        });
  },
  created: function () {
      this.get_quotes_from_db();
      this.displayNextQuote();
  },
  methods:{
    displayNextQuote:function(){
      if( this.available_quotes.length > 0 ) {

        //don't allow us to run dry before refueling!!!
        if( this.available_quotes.length < 20 ) {
          this.get_quotes_from_db();
        }

        //a quote just compelted displaying
        var dat = this.available_quotes.pop();
        var numberOfWordsInQuote = dat.quote.split(' ').length;
        var quoteDisplayTimeInSecs = Math.floor(numberOfWordsInQuote / 2 ) + 2;//use 1/2 of a second to display a word

        //update the UI
        this.quote = dat;
        //find what 1 secs corresponds to in percontage
        var incrementPerSecond = 100 /quoteDisplayTimeInSecs;


        //we need to delay a little before doing animation, so that quote display transition will not cause quote meter to start reading while quote still not fully opacitied-out
        this.quotesBarWidth = 0;
        this.progressbar.set(0);
        this.incrementQuoteWidth( incrementPerSecond );

        //show next quote at the right time
        var vm = this;
        setTimeout(function(){
          //we do not want animation when setting to zero
          if(vm.ongoingTimeout){
            clearTimeout( vm.ongoingTimeout );
          }
          vm.displayNextQuote();
        }, (quoteDisplayTimeInSecs*1000) )
      }else{

        //wait a while before displaying next quote so that if it was network not copleted as at present time, network should have completed by time we retrying to show next quote
        var vm = this;
        setTimeout(function(){
          vm.displayNextQuote();
        },1000)

      }
    },
    incrementQuoteWidth:function(increment){
        this.quotesBarWidth += increment;
        this.progressbar.animate(this.quotesBarWidth/100);
        //ensure not to offshoot 100
        if((this.quotesBarWidth+increment) <= 100){
          var vm = this;
          this.ongoingTimeout = setTimeout(function(){
            vm.incrementQuoteWidth( increment );
          }, 1000 )
        }
    },
    retry_ajax_request:function( xhr ){
        xhr.retry++;
        if( xhr.retry < 10 ){
          xhr.open("GET", xhr.url, true);
          xhr.send();
        }else{
          console.log(`not retrying (${xhr.retry} >= 10) `);
        }
    },
    get_quotes_from_db:function( xhr = null ){
      var vm = this;
      if( xhr == null ){
        xhr = new XMLHttpRequest();
        xhr.url = `${this.webserver_url}/git-collaborations/tcexam/admin/code/tce_quotes.php`;
        console.log(xhr.url);
        xhr.open("GET", xhr.url, true);
        xhr.retry = 1;
      }

      //Send the proper header information along with the request
      // xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onreadystatechange = function() {//Call a function when the state changes.
          if(xhr.readyState == 4) {
              if(xhr.status == 200) {
                // Request finished. Do processing here.
                //eslint-disable-next-line
                try{
                  vm.available_quotes = [...JSON.parse( xhr.responseText ) ];
                }
                catch(Ex){
                  console.log(Ex);
                  console.log(xhr);
                  console.log(xhr.responseText);
                  vm.retry_ajax_request(xhr);
                }
              } else {
                console.log(xhr);
                console.log(xhr.status);
                console.log(xhr.responseText);
                vm.retry_ajax_request(xhr);
              }
          }else{
                //redo the request
                //Value State Description
              // 0 UNSENT  Client has been created. open() not called yet.
              // 1 OPENED  open() has been called.
              // 2 HEADERS_RECEIVED  send() has been called, and headers and status are available.
              // 3 LOADING Downloading; responseText holds partial data.
              // 4 DONE  The operation is complete.
          }
      }

      xhr.send("foo=bar&lorem=ipsum"); 
      // xhr.send('string'); 
      // xhr.send(new Blob()); 
      // xhr.send(new Int8Array()); 
      // xhr.send({ form: 'data' }); 
      // xhr.send(document);
    },
  }
}
</script>
