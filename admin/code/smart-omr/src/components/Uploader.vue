<template>
  <div class="upload" v-show="!isOption">
    <div class="absolute w-full h-full">
      <div v-if="showUploadUI && !files.length">
          <div class="dropbox text-center absolute p-5 w-full h-full">
            <h4>Drop folder to upload...</h4>
            <!-- <br/>
            OR
            <br/>
            <label for="omrfile" class="btn btn-lg btn-primary">Select Files</label> -->
            <div v-show="$refs.upload && $refs.upload.dropActive" class="drop-active mt-5">
              <h3 class="font-sans">Drop folder to upload</h3>
            </div>
          </div>
      </div>

      <table class="mostly-customized-scrollbar drop-table overflow-auto h-full w-full pb-24 block absolute">
        <thead v-show="files.length">
          <tr class="bg-pink-light w-full font-sans">
            <th> #</th>
            <th>Thumbnail</th>
            <th>Name</th>
            <th>Size</th>
            <th>Speed</th>
            <th>Status</th>
            <th class="w-full">Action</th>
          </tr>
        </thead>
        <tbody class="">
            <tr v-for="(file, index) in files" :key="file.id"  class=" font-mono text-sm hover:bg-blue-lighter cursor-pointer "  :class="{'bg-red-light': file.error && file.error!='denied'}">
              <td>{{index+1}}</td>
              <td class="whitespace-no-wrap">
                <img v-if="file.thumb" :src="file.thumb" width="40" height="auto" />
                <span v-else>No Image</span>
              </td>
              <td>
                <div class="filename whitespace-no-wrap">
                  {{file.name}}
                </div>
                <div :id="file.id" class="progress" v-if="file.active || file.progress !== '0.00'">
                  <div :class="{'progress-bar': true, 'progress-bar-striped': true, 'bg-green': Number(file.progress) != 100 , 'bg-danger': file.error, 'progress-bar-animated': file.active}" role="progressbar" :style="{width: file.progress + '%'}">
                    {{file.progress}}%
                  </div>
                </div>
              </td>
              <td class="whitespace-no-wrap">{{file.size | formatSize}}</td>
              <td class="whitespace-no-wrap">{{file.speed | formatSize}}</td>

              <td class="whitespace-no-wrap" v-if="file.error">error: {{file.error}}</td>
              <td class="whitespace-no-wrap" v-else-if="file.success">
                <img width="25px" src="@/assets/images/circle-checkmark-512.png" alt="">
              </td>
              <td class="whitespace-no-wrap" v-else-if="file.active">active</td>
              <td class="whitespace-no-wrap" v-else></td>

              <td>
                <div class="dropdown">
                  <button class="btn btn-secondary btn-sm dropdown-toggle" type="button">
                    Action
                  </button>
                  <div class="dropdown-content ">
                    <a :class="{'dropdown-item': true, disabled: !file.active}" href="#" @click.prevent="file.active ? $refs.upload.update(file, {error: 'cancel'}) : false">Cancel</a>
                    <a class="dropdown-item" href="#" v-if="file.active" @click.prevent="$refs.upload.update(file, {active: false})">Abort</a>
                    <a class="dropdown-item" href="#" v-else-if="file.error && file.error !== 'compressing' && $refs.upload.features.html5" @click.prevent="$refs.upload.update(file, {active: true, error: '', progress: '0.00'})">Retry upload</a>
                    <a :class="{'dropdown-item': true, disabled: file.success || file.error === 'compressing'}" href="#" v-else-if="!file.success" @click.prevent="file.success || file.error === 'compressing' ? false : $refs.upload.update(file, {active: true}) ">Upload</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" @click.prevent="$refs.upload.remove(file)">Remove</a>
                  </div>
                </div>
              </td>
            </tr>
        </tbody>
      </table>
    </div>

    <!-- name="main-grey-background"  -->

    <div>
      <!-- :put-action="putAction" -->
      <file-upload
        :name="name"
        :post-action="postAction"
        :extensions="extensions"
        :accept="accept"
        :multiple="multiple"
        :directory="directory"
        :size="size || 0"
        :thread="thread < 1 ? 1 : (thread > 10 ? 10 : thread)"
        :headers="headers"
        :drop="drop"
        :drop-directory="dropDirectory"
        :add-index="addIndex"
        v-model="files"
        @input-filter="inputFilter"
        @input-file="inputFile"
        ref="upload">
          <div v-show="!filesAvailable" class="btn btn-primary dropdown-toggle cursor-pointer" >
            <i class="fa fa-plus"></i>
            Select
          </div>
        </file-upload>
    </div>

    <transition
      name="main-grey-background"
      enter-class="animated bounceInUp"
      enter-active-class="animated bounceInUp"
      enter-to-class="animated bounceInUp"
    >
      <div v-show="filesAvailable" :class="greyMainBackground" class="main-bottom-container">
        <div v-show="showStartUpoadButton || showStartAllOver || showStopUpoadButton" class="relative w-1/6">
          <transition-group name="action-buttons" >
            <button
              :key="1"
              type="button"
              class="relative btn block w-32 h-6 m-1"
              v-show="
              ( showStartUpoadButton || enableResendUpload )
              && (!$refs.upload || !$refs.upload.active)
              && ( this.ongoingServerPollTimestamp && this.reportedJobs.indexOf(this.ongoingServerPollTimestamp) )
              "
              @click.prevent="doUpload"
            >
              <!-- <i class="fa fa-arrow-up" aria-hidden="true"></i> -->
              {{ allUploadsCompleted ? 'Re-Upload All' : 'Upload All' }}

            </button>

            <button
              :key="2" type="button"
              class="relative btn block w-32 h-6 m-1"
              v-show=" showStartAllOver && ( $refs.upload && $refs.upload.uploaded ) "
              @click.prevent="startAllOver"
            >
              <!-- <i class="fa fa-arrow-up" aria-hidden="true"></i> -->
              Start All Over
            </button>

            <button
              :key="3" type="button"
              class="relative btn button block w-32 h-6 m-1"
              v-show="showStopUpoadButton && ($refs.upload && $refs.upload.active) "
              @click.prevent="stopUploads"
            >
              <!-- <i class="fa fa-stop" aria-hidden="true"></i> -->

              Stop Upload
            </button>
          </transition-group>

        </div>


        <transition>
          <div v-if="showGreenWorkingBackground" :class="greenWorkingBackground" class='animateAll green-working-background'>
            <!-- <div class="inline">
              <div class="lds-roller h-4">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
              </div>
            </div> -->
              <transition>
                <img v-if="displayLoadingImage" class="inline self-center" width="70px" src="@/assets/images/loader.svg">
              </transition>

            <div :class="main_holdin_container" class="animateAll self-center">

              <transition mode="in-out" appear>
                <div class="progress_text_holder" :class="current_progress_text_class">
                    <span
                      :class="current_spanprogresstext_class"
                      class="progress_text"
                      v-show="true"
                      key="serverStatusText"
                      v-html="serverStatusText"
                    >
                    </span>
                </div>
              </transition>

              <div  class="relative w-full flex main-progress-area-holder flex-col">

                <transition>
                  <div v-if="showPercentageCompletion" class="animateAll mt-16 w-full h-full pin-x self-center text-center absolute font-sans font-bold text-3xl" >
                    {{markingPercentageProgress}}%
                  </div>
                </transition>

                <div v-if="showProgressHolder" :class="current_progress_control_container_class">
                  <div :class="morphable_LoadingClass" class="-ml-12 self-center absolute circle-loader">
                    <div v-if="showCompletionCheckmark" class="checkmark draw"></div>
                  </div>
                </div>

                <div v-if="showQuotes" class="h-auto relative flex text-white w-full">
                  <quotes :webserver_url="webserver_url"></quotes>
                </div>

                <div class="self-center report relative" v-if="showFinalStatus">
                  <div>
                    Total scripts marked: <span class="label m-1 inline-block">124</span>
                  </div>
                  <div>
                    Total units used: <span class="label m-1 inline-block">103</span>
                  </div>
                  <div>
                    Total units left: <span class="label m-1 inline-block">433</span>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </transition>

      </div>
    </transition>
  </div>
</template>

<style type="text/css">
  /*
  //loader img disappears
  //the light green background enlarges to fill the screen
  //red colored progress stuff becomes the progress bar
  //quotes appear underneath the progressbar
  //center of progressbar shows percentage completion
  //at anytime that error occurs along the way, clear evrrything and use Swal to handle it
  */
</style>

<script>
import Quotes from '../components/Quotes.vue'
import Vue from 'vue'
import FileUpload from 'vue-upload-component'

Vue.filter('formatSize', function (size) {
  if (size > 1024 * 1024 * 1024 * 1024) {
    return (size / 1024 / 1024 / 1024 / 1024).toFixed(2) + ' TB'
  } else if (size > 1024 * 1024 * 1024) {
    return (size / 1024 / 1024 / 1024).toFixed(2) + ' GB'
  } else if (size > 1024 * 1024) {
    return (size / 1024 / 1024).toFixed(2) + ' MB'
  } else if (size > 1024) {
    return (size / 1024).toFixed(2) + ' KB'
  }
  return size.toString() + ' B'
})

export default {
  components: {
    FileUpload,
    Quotes
  },
  props:[
    'showStartUpoadButton',
    'showStartAllOver',
    'showStopUpoadButton',
    'startMarking',
    'serverStatusText',
    'current_progress_control_container_class',
    'current_bottom_main_grey_background_class',
    'current_bottom_green_background_class',
    'current_progress_text_class',
    'current_spanprogresstext_class',
    'main_holdin_container',
    'showProgressHolder',
    'showQuotes'
  ],
  data() {
    return {
      files: [],
      filesAvailable: false,
      quotes:[],
      show_quote: true,
      accept: 'image/png,image/gif,image/jpeg',
      extensions: 'jpg,jpeg,png',
      // extensions: ['gif', 'jpg', 'jpeg','png', 'webp'],
      // extensions: /\.(gif|jpe?g|png|webp)$/i,

      // var full = location.protocol+'//'+location.hostname+(location.port ? ':'+location.port: '');
      // xhr.url="http://localhost/git-collaborations/tcexam/admin/code/tce_quotes.php";
      webserver_url: location.protocol+'//'+location.hostname,
      server_script_path: 'git-collaborations/tcexam/admin/code/tce_import_omr_answers_bulk_smart_processor.php',
      status_upgrade_script_path: 'git-collaborations/tcexam/admin/code/tce_import_omr_answers_bulk_smart_processor_status.php',
      // putAction: '/upload/put',
      minSize: 1024,
      size: 1024 * 1024 * 50,
      multiple: true,
      computationOngoin:false,
      directory: false,
      drop: true,
      dropDirectory: true,
      addIndex: false,
      thread: 10,
      name: 'omrfile',
      headers: {
        // 'X-Csrf-Token': 'xxxx',
      },
      autoCompress: 1024 * 1024,
      uploadAuto: false,
      isOption: false,
      addData: {
        show: false,
        name: '',
        type: '',
        content: '',
      },
      editFile: {
        show: false,
        name: '',
      },

      lasyYScroll: undefined,
      ongoingServerPollTimestamp: undefined,
      currentMarkingSessionAbortedWithError: false,
      reportedJobs: [],
      intervalQueryStatus: 7000,

      /******** C O N D I T I O N A L    D I S P L A Y S ********/

      showUploadUI: true,
      displayLoadingImage: true,
      showLoadingMode: false,
      showPercentageCompletion: false,
      showCompletionCheckmark: false,
      showFinalStatus: false,
      showGreenWorkingBackground: false,

      isMounted: false,
      markingPercentageProgress: 1,


      /******** C S S    C L A S S E S ********/

      current_working_backround_class: {}

      /******** C S S    C L A S S E S ********/

    }
  },
  mounted:function(){
    let that = this;
    this.$nextTick(function(){
      this.ongoingServerPollTimestamp = new Date().getTime();
      this.isMounted = true;
    });
  },
  computed:{
    enableResendUpload: function(){
      //after compelted mrking successfully, we need tihis to allow be able to do another makring
      return this.files && this.files.length > 0;
    },
    postAction: function(){
      return `${this.webserver_url}/git-collaborations/tcexam/admin/code/tce_import_omr_answers_bulk_smart_processor.php`;
    },
    allUploadsCompleted:function(){
      if(this.isMounted){
        return (this.$refs.upload && this.$refs.upload.uploaded && this.files.length > 0);
      }
    },
    isCurrentlyUploading:function(){
      if(this.isMounted){
        return this.$refs.upload.active;
      }
    },
    morphable_LoadingClass:function(){
      this.showCompletionCheckmark = !this.showLoadingMode;
      this.showPercentageCompletion = this.showLoadingMode;
      if( this.showLoadingMode ){
        return { "load-complete" : false }
      }else{
        return { "load-complete" : true }
      }
    },
    greenWorkingBackground:function(){
      return this.current_bottom_green_background_class;
    },
    greyMainBackground:function(){
      return this.current_bottom_main_grey_background_class;
    },
    uploadCompleted:function(){

      //try optimize for when multiple progress reports will be updating
      if(this.computationOngoin){
        return;
      }

      this.computationOngoin = true;

      let complete = 0;
      for (var i = this.files.length - 1; i >= 0; i--) {
        let prog = Number(this.files[i].progress);
        complete += (prog == 100) ? 1 : 0;

        //retry it if it stopped uploading
        //we are checking preence of xhr because that is what confirms that
        //there was a failed attempt to upload the file (conf. completely
        //unattempted file e.g. when file rejected coz extension is part of
        //blacklisted)
        if(prog<100 && this.files[i].xhr && !this.files[i].xhr.readyState==4){
          //automatically retry upload
          this.$refs.update(this.files[i], {active: true, error: '', progress: '0.00'});
        }
      }

      this.computationOngoin = false;
      return complete == this.files.length;
    }
  },
  watch: {
    allUploadsCompleted:function(currVal,oldval){
      if(currVal){
        this.sendServerUploadsCompletedMessage();//so server can set some specific error meesaages if need be
        //notify users if there are erroneous fils too
        for (var i = this.files.length - 1; i >= 0; i--) {
          if(this.files[i].error){
            //empty server status text so to thr green background is not renderes (watchers!)
            this.currentMarkingSessionAbortedWithError= true;
            this.$emit('serverStatusText', null);
            this.$emit('alert', 'You have file(s) with error. Please check the files you added for marking and try again' , 1 );
            return;
          }
        }
      }
    },
    'addData.show'(show) {
      if (show) {
        this.addData.name = ''
        this.addData.type = ''
        this.addData.content = ''
      }
    },
    startMarking: function(){
      this.doUITransitionToStartMarking();
    },
    files: function(){
      this.$emit('files_change' , this.files );
      this.filesAvailable = this.files && (this.files.length > 0);
      this.showOrHideGreenWorkingBackground();
    },
    serverStatusText: function(){
      this.showOrHideGreenWorkingBackground();
    },
    'isCurrentlyUploading': function( new_val , old_val ){
      if( new_val ) {
        //don't redo if there is an ongoing polling
        if(!!this.ongoingServerPollTimestamp == false) {
          this.ongoingServerPollTimestamp = new Date().getTime();
          this.start_polling_server_for_status_updates();
        }
      }else{

        //ensure not to trigger on first vm mount - nothing is there to poll sever for yet
        if((this.$refs.upload && this.$refs.upload.uploaded && this.files.length > 0 ) && this.ongoingServerPollTimestamp) {
          //do one last request, so that if race condition makes staus polling to not return anyting, there is now a single final last chance to get status for this just concluded stuff
          this.start_polling_server_for_status_updates( null, true, Number(this.ongoingServerPollTimestamp) );
        }
      }
    }
  },
  methods: {
    stopUploads:function(){
      try{
        this.$refs.upload.active = false;
      }catch(Ex){
      }
      this.$emit('serverStatusText' , "");
    },

    doUpload:function(){
      //don't forget to set serverstatustext, so that the necessary ui changes will be effected (e.g. displying of the progress status text area)
      this.currentMarkingSessionAbortedWithError= false;
      this.displayLoadingImage = true;

      if(this.allUploadsCompleted) {
        //we are reuploading...
        if(this.$refs.upload && this.$refs.upload.uploaded && this.files.length > 0) {
            //make it retry
            for (var i = this.files.length - 1; i >= 0; i--) {
            //automatically retry upload
            this.$refs.upload.update(this.files[i], {
              active: true,
              error: '',
              success: false,
              progress: '0.00'
            });
          }
        }
        this.$refs.upload.active = true
        this.$emit('serverStatusText' , 'Uploading files to server...');
      } else{
        //ths is the very begniiing
        this.$refs.upload.active = true
        this.$emit('serverStatusText' , 'Uploading files to server...');
      }
    },

    handle_server_status_message:function( message , job_id  /* , promise */ ){

      //we follow this convention: starting with 'e:' means error. while startig with 's:' means it is a status meaasge

      let prefix = message.substr( 0, 2 );
      //treat according to if it is tagged or not (tagged is either s: OR e: for status message OR error message respectively) - i.e. 2nd char is ':'
      let _mssg = message[1] == ':' ? message.substr( 2 ) :  message;

      if( prefix == 's:' ) {
        try{

          let realmssg = JSON.parse( _mssg );

          if(realmssg.marking_completed_successfully){
            this.$emit('_markingCompletedSuccessfully');
            this.displayLoadingImage = false;//this ting runs down CPU!! So stop it as soon as possible wth chances of even if its container is displayed...also ensures cleaner UI: no image unessaruly
            this.ongoingServerPollTimestamp = undefined;
          }else{
              this.$emit('serverStatusText' , realmssg.status_text);
              this.markingPercentageProgress = realmssg.percentage_progress;
          }
        }catch(Ex){
          this.reportErrorAsAlertPopoup(`Error occured: ${Ex} (${_mssg})`);
        }
      }else{
        this.ongoingServerPollTimestamp = undefined;
        this.reportErrorAsAlertPopoup(_mssg);
      }

      // promise.resolve();
    },

    reportErrorAsAlertPopoup:function( _mssg ){
      //destroy it, so that if there is any ongoing server request as at time this was set,
      //the logic will discountence server response
      this.ongoingServerPollTimestamp = undefined;
      this.$emit('_showErrorThatTerminatesOngoingMarking',_mssg);
      this.displayLoadingImage = false;//this ting runs down CPU!! So stop it as soon as possible wth chances of even if its container is displayed...also ensures cleaner UI: no image unessaruly
    },

    startAllOver: function(){
        //means we have already uploaded some stuff. We want to redo it
        //we need to reset stuff too
        this.$emit('_startAllOver' , null);
    },

    showOrHideGreenWorkingBackground: function(){
      this.showGreenWorkingBackground =
      ( this.isCurrentlyUploading )
      ||
      ( ( this.serverStatusText != undefined ) && ( this.serverStatusText.length > 0 ) );

      // if(showGreenWorkingBackground){
      //   this.current_bottom_green_background_class = this.parent.data.;
      // }
    },

    inputFilter(newFile, oldFile, prevent) {
      if (newFile && !oldFile) {
        // Before adding a file
        // 添加文件前
        // Filter system files or hide files
        // 过滤系统文件 和隐藏文件
        if (/(\/|^)(Thumbs\.db|desktop\.ini|\..+)$/.test(newFile.name)) {
          return prevent()
        }
      }
      if (newFile && (!oldFile || newFile.file !== oldFile.file)) {
        // Create a blob field
        // 创建 blob 字段
        newFile.blob = ''
        let URL = window.URL || window.webkitURL
        if (URL && URL.createObjectURL) {
          newFile.blob = URL.createObjectURL(newFile.file)
        }
        // Thumbnails
        // 缩略图
        newFile.thumb = ''
        if (newFile.blob && newFile.type.substr(0, 6) === 'image/') {
          newFile.thumb = newFile.blob
        }
      }
    },
    // add, update, remove File Event
    inputFile(newFile, oldFile) {

      //this happens if we are doing another round of marking after the firs tone successful
      if(!!this.ongoingServerPollTimestamp == false ){
        this.ongoingServerPollTimestamp = new Date().getTime();
      }

      if( newFile && (oldFile == undefined )) {

        let newData = {
          'job_id': this.ongoingServerPollTimestamp,
          total_number_files: this.files ? this.files.length : 0,
          filename: newFile.name,
          error:'true',
        }

        this.$refs.upload.update( newFile, { data: newData } )

      }

      if (newFile && oldFile) {
        // update
        if (newFile.active && !oldFile.active) {
          // beforeSend
          // min size
          if (newFile.size >= 0 && this.minSize > 0 && newFile.size < this.minSize) {
            this.$refs.upload.update(newFile, { error: 'size' })
          }
        }
        if (newFile.progress !== oldFile.progress) {
          // progress
          // console.log('progress:', oldFile , newFile)
          // this.files = this.$refs.files;
          if (Number(newFile.progress) == 100) {
            // console.log(newFile.xhr);
          }
        }
        if (newFile.error && !oldFile.error) {
          // error
        }
        if (newFile.success && !oldFile.success) {
          // success
        }

        if (newFile && oldFile && !newFile.active && oldFile.active) {
          // Get response data
          if (newFile.xhr && newFile.response) {
            if(newFile.response.indexOf("will start marking") >= 0) {
              //signal that server is waiting for go-ahead command to commence marking
              if( this.ongoingServerPollTimestamp && this.reportedJobs.indexOf(this.ongoingServerPollTimestamp) == -1 ) {
                this.reportedJobs.push(this.ongoingServerPollTimestamp)
                this.$emit('_allUploadsCompleted')
                this.sendServerCommand('startProessingUploadedScripts')
                console.log("server now commanded")
              } else {
                // no ongoing jobs or job already submitted
              }
            } else {
              if(newFile.response.indexOf("expecting more data") >= 0){
                //server is still expecting more files
              } else {
                var temp = document.createElement("div");
                temp.innerHTML = newFile.response;
                console.error("Error: ", ( temp.textContent || temp.innerText || "" ), newFile.xhr )
              }
            }
          }
        }

        var _node_ = document.getElementById(newFile.id);
        if(_node_){
          _node_.scrollIntoView();
        }
      }

      if (!newFile && oldFile) {
        if (oldFile.response) {
          // console.log('response: ' + oldFile.response)
          // console.log( oldFile.xhr );
        }
        // remove
        if (oldFile.success && oldFile.response.id) {
          // $.ajax({
          //   type: 'DELETE',
          //   url: '/upload/delete?id=' + oldFile.response.id,
          // })
        }
      }
      // Automatically activate upload
      if (Boolean(newFile) !== Boolean(oldFile) || oldFile.error !== newFile.error) {
        if (this.uploadAuto && !this.$refs.upload.active) {
          this.$refs.upload.active = true
        }
      }
    },

    doUITransitionToStartMarking:function(){
      /*
      //loader img disappears
      //the light green background enlarges to fill the screen
      //red colored progress stuff becomes the progress bar
      //quotes appear underneath the progressbar
      //center of progressbar shows percentage completion
      //at anytime that error occurs along the way, clear evrrything and use Swal to handle it
      */
     this.$parent.doUITransition("All Uploads Successful");
    },

    get_quotes_from_db:function(){
      var vm = this;
      var xhr = new XMLHttpRequest();
      xhr.url=`${this.webserver_url}/git-collaborations/tcexam/admin/code/tce_quotes.php"`;
      xhr.open("GET", xhr.url, true);
      xhr.retry = 1;

      //Send the proper header information along with the request
      // xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onreadystatechange = function() {//Call a function when the state changes.
          if(xhr.readyState == 4) {
              if(xhr.status == 200) {
                // Request finished. Do processing here.
                // console.log(xhr.responseText);
                //eslint-disable-next-line
                try{
                  var quotes = [...JSON.parse( xhr.responseText ) ]
                  vm.quotes.splice( 0, 0, quotes );
                }
                catch(Ex){
                  console.log(Ex);
                  console.log(xhr);
                }
              } else {
                console.log(xhr);
                console.log(xhr.status);
                //redo the request
                this.retry++;
                if( this.retry < 10 ){
                  this.open("GET", xhr.url, true);
                  this.send();
                }
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

    start_polling_server_for_status_updates: function( xhr = null , isFinalRequest = null , _ongoingServerPollTimestamp = null ){
      var vm = this;
      if( xhr == null ) {
        xhr = new XMLHttpRequest();
        xhr.url=`${this.webserver_url}/${this.status_upgrade_script_path}`;
        // xhr.url=`${this.webserver_url}/${this.status_upgrade_script_path}?getStatus=true&job_id=${this.ongoingServerPollTimestamp}`;
        xhr.retry = 1;
        xhr.ongoingServerPollTimestamp = this.ongoingServerPollTimestamp;
        xhr.isFinalRequest = isFinalRequest;
      }

      xhr.onreadystatechange = function() {//Call a function when the state changes.
          if(xhr.readyState == 4) {
              if(xhr.status == 200) {

                // Request finished. Do processing here.
                try {

                      if(vm.currentMarkingSessionAbortedWithError){
                        return;
                      }

                      //when undefned, it means some request came back before this status update and there is no new request (which would have set the undefined to the new jobid)
                      //so we only need to discontence this one if and only if there is actually a new, valid, marking going on (i.e. ongoingServerPollTimestamp is set to another valid jobid intwger)
                      if( (xhr.ongoingServerPollTimestamp == vm.ongoingServerPollTimestamp) || !!vm.ongoingServerPollTimestamp == false ) {

                        //emptiness means non of the other file upload requests has had the chance to write to the db while this one is making his own request
                        if(xhr.responseText.length > 1){
                          vm.handle_server_status_message(xhr.responseText , xhr.ongoingServerPollTimestamp);
                        }

                        //we only need to resend if we are still the regigning job in town!
                        //prevent sending when they are both undefined (as in isFInalRequest)
                        if( xhr.ongoingServerPollTimestamp && (xhr.ongoingServerPollTimestamp == vm.ongoingServerPollTimestamp) ){
                          setTimeout( function() {
                              vm.start_polling_server_for_status_updates( xhr )
                          } , vm.intervalQueryStatus )
                        }
                      }else{
                        if(isFinalRequest){
                          vm.handle_server_status_message(xhr.responseText , xhr.ongoingServerPollTimestamp);
                        }
                    }
                }
                catch(Ex){
                  console.log(Ex);
                  console.log(xhr);
                }

              } else {
                console.log(xhr);
                console.log(xhr.status);
                //redo the request
                this.retry++;
                if( this.retry < 3 ){
                  if( xhr.ongoingServerPollTimestamp == vm.ongoingServerPollTimestamp ){
                    vm.start_polling_server_for_status_updates( xhr )
                  }
                }
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

      xhr.open("POST", xhr.url, true);
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.send(`getStatus=true&job_id=${_ongoingServerPollTimestamp ? _ongoingServerPollTimestamp : this.ongoingServerPollTimestamp}&isFinalRequest=${!!isFinalRequest}`);
      // xhr.send('string');
      // xhr.send(new Blob());
      // xhr.send(new Int8Array());
      // xhr.send({ form: 'data' });
    },

    sendServerUploadsCompletedMessage: function( xhr = null ){

      var vm = this;
      if( xhr == null ){
        xhr = new XMLHttpRequest();
        xhr.url=`${this.webserver_url}/${this.server_script_path}`;
        xhr.retry = 1;
        xhr.sentData = `all_available_files_uploaded=true&job_id=${this.ongoingServerPollTimestamp}`;
      }

      xhr.onreadystatechange = function() {//Call a function when the state changes.
          if(xhr.readyState == 4) {
              if(xhr.status == 200) {
                //we good
              } else {
                console.log(xhr);
                console.log(xhr.status);
                //redo the request
                this.retry++;
                if( this.retry < 10 ){
                  vm.sendServerUploadsCompletedMessage( xhr )
                }
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


      xhr.open("POST", xhr.url, true);
      //Send the proper header information along with the request
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.send(xhr.sentData);
      // xhr.send('string');
      // xhr.send(new Blob());
      // xhr.send(new Int8Array());
      // xhr.send({ form: 'data' });
      // xhr.send(document);
    },

    sendServerCommand: function( command , xhr = null ) {

      var vm = this;

      if( xhr == null ) {

        var query = "";
        let addendum = "";

        switch (command) {
          case 'startProessingUploadedScripts':
            query = "startProessingUploadedScripts=true&job_id=" + this.ongoingServerPollTimestamp;
            addendum = "?startmarking=true";
            break;
        }

        xhr = new XMLHttpRequest();
        xhr.url=`${this.webserver_url}/${this.server_script_path}${addendum}`;
        // xhr.sentData = `startProessingUploadedScripts=true&job_id=${Number(this.ongoingServerPollTimestamp)}`;
        xhr.sentData = query;
        xhr.retry = 1;
      }

      xhr.onreadystatechange = function() {//Call a function when the state changes.
          if(xhr.readyState == 4) {
              if(xhr.status == 200) {
                //any errors should have been handled by the server
              } else {
                console.log(xhr);
                console.log(xhr.status);
                //redo the request
                this.retry++;
                if( this.retry < 10 ){
                  vm.sendServerCommand( command , xhr )
                }
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

      xhr.open("POST", xhr.url, true);
      //Send the proper header information along with the request
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.send(xhr.sentData);
      // xhr.send('string');
      // xhr.send(new Blob());
      // xhr.send(new Int8Array());
      // xhr.send({ form: 'data' });
      // xhr.send(document);
    },
  }
}
</script>
