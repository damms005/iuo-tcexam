<template>
  <div class="upload" v-show="!isOption">
    <div class="absolute w-full h-full">
      <div v-if="showUploadUI && !files.length">
        <td colspan="7">
          <div class="dropbox text-center absolute p-5 w-full h-full">
            <h4>Drop folder to upload...</h4>
            <!-- <br/>or -->
            <!-- <label :for="name" class="btn btn-lg btn-primary">Select Files</label> -->
            <div v-show="$refs.upload && $refs.upload.dropActive" class="drop-active mt-5">
              <h3 class="font-sans">Drop folder to upload</h3>
            </div>
          </div>
        </td>
      </div>

      <table class="mostly-customized-scrollbar drop-table overflow-auto h-full w-full pb-24 block absolute">
        <thead v-show="files.length">
          <tr class="bg-pink-light w-full">
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
            <tr v-for="(file, index) in files" :key="file.id" :class="{'bg-red-light': file.error && file.error!='denied'}">
              <td>{{index+1}}</td>
              <td>
                <img v-if="file.thumb" :src="file.thumb" width="40" height="auto" />
                <span v-else>No Image</span>
              </td>
              <td>
                <div class="filename">
                  {{file.name}}
                </div>
                <div :id="file.id" class="progress" v-if="file.active || file.progress !== '0.00'">
                  <div :class="{'progress-bar': true, 'progress-bar-striped': true, 'bg-green': Number(file.progress) != 100 , 'bg-danger': file.error, 'progress-bar-animated': file.active}" role="progressbar" :style="{width: file.progress + '%'}">
                    {{file.progress}}%
                  </div>
                </div>
              </td>
              <td>{{file.size | formatSize}}</td>
              <td>{{file.speed | formatSize}}</td>

              <td v-if="file.error">{{file.error}}</td>
              <td v-else-if="file.success">success</td>
              <td v-else-if="file.active">active</td>
              <td v-else></td>

              <td>
                <div class="dropdown">
                  <button class="btn btn-secondary btn-sm dropdown-toggle" type="button">
                    Action
                  </button>
                  <div class="dropdown-content ">
                    <a :class="{'dropdown-item': true, disabled: file.active || file.success || file.error === 'compressing'}" href="#" @click.prevent="file.active || file.success || file.error === 'compressing' ? false :  onEditFileShow(file)">Edit</a>
                    <a :class="{'dropdown-item': true, disabled: !file.active}" href="#" @click.prevent="file.active ? $refs.upload.update(file, {error: 'cancel'}) : false">Cancel</a>
                    <a class="dropdown-item" href="#" v-if="file.active" @click.prevent="$refs.upload.update(file, {active: false})">Abort</a>
                    <a class="dropdown-item" href="#" v-else-if="file.error && file.error !== 'compressing' && $refs.upload.features.html5" @click.prevent="$refs.upload.update(file, {active: true, error: '', progress: '0.00'})">Retry upload</a>
                    <a :class="{'dropdown-item': true, disabled: file.success || file.error === 'compressing'}" href="#" v-else @click.prevent="file.success || file.error === 'compressing' ? false : $refs.upload.update(file, {active: true})">Upload</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" @click.prevent="$refs.upload.remove(file)">Remove</a>
                  </div>
                </div>
              </td>
            </tr>
        </tbody>
      </table>
    </div>

    <transition 
      enter-active-class="animated bounceInUp"
      leave-active-class="animated bounceOutDown"
    >
      <div v-show=" showDraggedInFiles " 
        :class="greyMainBackground" class="animateAll" 
      >
        <div class="hidden">
            <!-- :put-action="putAction" -->
            <file-upload
              class="btn btn-primary dropdown-toggle"
              :post-action="postAction"
              :extensions="extensions"
              :accept="accept"
              :multiple="multiple"
              :directory="directory"
              :size="size || 0"
              :thread="thread < 1 ? 1 : (thread > 5 ? 5 : thread)"
              :headers="headers"
              :data="data"
              :drop="drop"
              :drop-directory="dropDirectory"
              :add-index="addIndex"
              v-model="files"
              @nullblock-input-filter="inputFilter"
              @input-file="inputFile"
              ref="upload">
              <!-- <i class="fa fa-plus"></i> -->
              <!-- Select -->
            </file-upload>
        </div>
        <transition>
          <button type="button" class="button w-64 h-8 self-center" v-if=" showStartUpoadButton && (!$refs.upload || !$refs.upload.active) " @click.prevent="$refs.upload.active = true">
            <!-- <i class="fa fa-arrow-up" aria-hidden="true"></i> -->
            Start Uploads
          </button>
          <button type="button" class="button w-64 h-8 self-center mx-1 mr-1 ml-1" v-if="showStopUpoadButton" @click.prevent="$refs.upload.active = false">
            <!-- <i class="fa fa-stop" aria-hidden="true"></i> -->
            Stop Upload
          </button>
        </transition>

        <transition>
          <div :class="greenWorkingBackground" class='animateAll'>
            <!-- <div class="inline">
              <div class="lds-roller h-4">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>
              </div>
            </div> -->
            <transition>
              <img v-if="displayLoadingImage" class="inline" width="70px" src="@/assets/images/loader.svg">
            </transition>

            <div :class="progressHolder" class="animateAll main-holdin-container">
              
              <div @click="toggleProgressStatus" class="bg-red text-white text-sm rounded inline-block p-1"> 
                Progress 
              </div>

              <transition mode="out-in">
                <div class="serverStatusText text-center">
                    <span v-if="showServerStatusText" class="tracking-wide text-xs bg-red-lighter p-2 text-white-darkest rounded" nokey="serverStatusText" v-html="serverStatusText"></span>
                </div>
              </transition>

              <div  class="relative w-full flex main-progress-area-holder flex-col">

                <transition>
                  <div v-if="showPercentageCompletion" class="animateAll mt-16 w-full h-full pin-x self-center text-center absolute font-sans font-bold text-3xl" >16%</div>
                </transition>

                <div class="mt-5 w-32 h-32 block pin-x pin-y self-center text-center relative progress-control-container">
                  <div :class="morphable_LoadingClass" class="self-center absolute circle-loader">
                    <div v-if="showCompletionCheckmark" class="checkmark draw"></div>
                  </div>
                </div>

                <div class="h-auto relative flex text-white w-full">
                  <quotes :webserver_url="webserver_url"></quotes>                  
                </div>

                <div class="report">
                  <div>
                    Total scripts marked: 124
                  </div>
                  <div>
                    Total units used: 103
                  </div>
                  <div>
                    Total units left: 433
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
 
  .animateAll{
    transition: all 1s;
  }
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
    'showDraggedInFiles',
    'showStopUpoadButton',
    'startMarking',
    'serverStatusText',
    'showServerStatusText'
  ],
  data() {
    return {
      files: [],
      quotes:[],
      show_quote: true,
      accept: 'image/png,image/gif,image/jpeg,image/pdf,doc/pdf',
      extensions: 'gif,jpg,jpeg,png,webp,pdf',

      // var full = location.protocol+'//'+location.hostname+(location.port ? ':'+location.port: '');
      // xhr.url="http://localhost/git-collaborations/tcexam/admin/code/tce_quotes.php";
      webserver_url: location.protocol+'//'+location.hostname,

      // extensions: ['gif', 'jpg', 'jpeg','png', 'webp'],
      // extensions: /\.(gif|jpe?g|png|webp)$/i,
      minSize: 1024,
      size: 1024 * 1024 * 10,
      multiple: true,
      computationOngoin:false,
      directory: false,
      drop: true,
      dropDirectory: true,
      addIndex: false,
      thread: 3,
      name: 'omrfiles',
      postAction: 'http://localhost/git-collaborations/tcexam/nodejs/php/smart-marker.php',
      // putAction: '/upload/put',
      headers: {
        // 'X-Csrf-Token': 'xxxx',
      },
      data: {
        'job_id':Date.now(),
        error:'true'
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

      lasyYScroll:undefined,

      /******** C O N D I T I O N A L    D I S P L A Y S ********/
      
      displayLoadingImage: false,
      showLoadingMode: true,
      showPercentageCompletion: true,
      showCompletionCheckmark: false,
      showUploadUI: false,


      /******** C S S    C L A S S E S ********/

      //main grey background at default
      bottom_main_grey_background_class: {
        "bg-grey-light p-2 pin-b absolute h-24 w-full flex justify-center": true
      },

      //main grey background at start marking
      bottom_main_grey_background_class_start_marking_class: {
        "bg-grey-light p-2 pin-b absolute h-full w-full flex justify-center": true
      },
      
      bottom_green_background_class: {
        "justify-center border rounded p-4 bg-green-lightest flex w-3/4": true
      },

      bottom_green_background_class_start_marking_class: {
        "animateAll relative h-full justify-center border rounded p-4 bg-green-lightest flex w-3/4": true
      },

      progress_holder_class: {
        "border rounded self-center text-grey-dark relative overflow-hidden w-auto ml-16 p-2 h-34": true
      },

      progress_holder_class_start_marking_class: {
        "border rounded self-center text-grey-dark relative overflow-hidden w-full p-2 h-full": true
      },

      current_working_backround_class: {}

      /******** C S S    C L A S S E S ********/

    }
  },
  mounted:function(){
    this.$nextTick(function(){
      this.current_working_backround_class = this.bottom_green_background_class_start_marking_class;
    });
  },
  watch: {
    startMarking: function(){
      this.doUITransitionToStartMarking();
    },
  },
  computed:{
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
      return this.current_working_backround_class;
    },
    greyMainBackground:function(){
      return this.bottom_main_grey_background_class_start_marking_class;
    },
    progressHolder: function(){
      return this.progress_holder_class_start_marking_class;
    },
    uploadCompleted:function(){

      //try optimize for when multiple progress reports will be updating
      if(this.computationOngoin){
        console.log('ongoing..');
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
    'addData.show'(show) {
      if (show) {
        this.addData.name = ''
        this.addData.type = ''
        this.addData.content = ''
      }
    }
  },
  methods: {
    toggleProgressStatus:function(){
      this.showLoadingMode = !this.showLoadingMode;
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
            console.log(newFile.xhr);
          }
        }
        if (newFile.error && !oldFile.error) {
          // error
        }
        if (newFile.success && !oldFile.success) {
          // success
        }

        var _node_ = document.getElementById(newFile.id);
        if(_node_){
          _node_.scrollIntoView();
        }
      }
      if (!newFile && oldFile) {
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
     this.displayLoadingImage = false;
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
                console.log("done");
                console.log(xhr.responseText);
                //eslint-disable-next-line
                try{
                  var quotes = [...JSON.parse( xhr.responseText ) ]
                  vm.quotes.splice( 0, 0, quotes );
                }
                catch(Ex){
                  console.log(Ex);
                  console.log(xhr);
                  console.log(xhr.responseText);
                }
              } else {
                console.log(xhr);
                console.log(xhr.status);
                console.log(xhr.responseText);
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
    }
  }
}
</script>
