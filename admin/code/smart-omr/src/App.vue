<template>
  <div class="flex relative">
    <!-- — - --  -->

    <div style="width: 8%"></div>

    <div style="width: 95%" class>
      <div class="bg-grey-lightest rounded fixed w-4/5 pin-y overflow-visible m-5 border">
        <uploader
          v-if="1"
          @files_change="files_change"
          @serverStatusText="updateServerStatus"
          @alert="alert"
          @_allUploadsCompleted="_allUploadsCompleted"
          @_showErrorThatTerminatesOngoingMarking="_showErrorThatTerminatesOngoingMarking"
          @_markingCompletedSuccessfully="_markingCompletedSuccessfully"
          @_startAllOver="_startAllOver"
          class="bg-grey-lightest"
          ref="uploader"
          :showStartUpoadButton="showStartUpoadButton"
          :showStartAllOver="showStartAllOver"
          :showStopUpoadButton="showStopUpoadButton"
          :serverStatusText="serverStatusText"
          :startMarking="startMarking"
          :main_holdin_container="current_main_holdin_container_class"
          :showProgressHolder="realMarkingStartedSoShowProgressInFullscreen"
          :showQuotes="showQuotes"
          :current_bottom_main_grey_background_class="current_bottom_main_grey_background_class"
          :current_bottom_green_background_class="current_bottom_green_background_class"
          :current_progress_control_container_class="current_progress_control_container_class"
          :current_progress_text_class="current_progress_text_class"
          :current_spanprogresstext_class="current_spanprogresstext_class"
        ></uploader>
      </div>
    </div>

    <div
      v-if="showTestingGround"
      style="width: 5%"
      class="bg-green-lightest rounded absolute pin-r m-5 min-h-full p-2 border text-xs"
    >
      <label class="block cursor-pointer p-1 mt-1">
        All Uploads Successful
        <input name="status[]" type="radio" @click="change_status($event)">
      </label>
      <label class="block cursor-pointer p-1 mt-1">
        Show error
        <input name="status[]" type="radio" @click="change_status($event)">
      </label>
      <label class="block cursor-pointer p-1 mt-1">
        Marking completed successfully
        <input
          name="status[]"
          type="radio"
          @click="change_status($event)"
        >
      </label>
      <label class="block cursor-pointer p-1 mt-1">
        Start All Over
        <input name="status[]" type="radio" @click="change_status($event)">
      </label>

      <label class="block cursor-pointer p-1 mt-1">
        Mimick server processing files status messages
        <input
          type="checkbox"
          @click="toggleGeneratingLoremIpsum"
        >
      </label>

      <div class="p-1 m-1 mt-12 border rounded bg-black text-white" v-html="status"></div>
    </div>
  </div>
</template>

<script>
import Uploader from "./components/Uploader.vue";
import loremIpsum from "lorem-ipsum";
import Swal from "sweetalert2";

export default {
  components: {
    Uploader
  },
  data() {
    return {
      showTestingGround: false,
      files: [],
      accept: "image/png,image/gif,image/jpeg,image/pdf,doc/pdf",
      extensions: "gif,jpg,jpeg,png,webp,pdf",
      // extensions: ['gif', 'jpg', 'jpeg','png', 'webp'],
      // extensions: /\.(gif|jpe?g|png|webp)$/i,
      minSize: 1024,
      size: 1024 * 1024 * 10,
      multiple: true,
      directory: false,
      drop: true,
      dropDirectory: true,
      addIndex: false,
      thread: 3,
      name: "file",
      postAction: "/upload/post",
      // putAction: '/upload/put',
      headers: {
        "X-Csrf-Token": "xxxx"
      },
      data: {
        _csrf_token: "xxxxxx"
      },
      autoCompress: 1024 * 1024,
      uploadAuto: false,
      isOption: false,
      addData: {
        show: false,
        name: "",
        type: "",
        content: ""
      },
      editFile: {
        show: false,
        name: ""
      },
      status: "...",

      /******** C O N D I T I O N A L    D I S P L A Y S ********/

      showStartUpoadButton: true,
      showStartAllOver: true,
      realMarkingStartedSoShowProgressInFullscreen: false,
      showQuotes: true,
      showStopUpoadButton: true,

      serverStatusText: "",
      startMarking: true,
      startMimickingServerSentStatus: true,
      mimickeryIntervalReference: null,

      /******** C S S    C L A S S E S ********/

      //1. main grey background at default
      //note that some of the classes are not necessarily for css rules, but simply markers that
      //are helpful for dom inspection @debugging, so we know which css class is swapped in at any partuclar time
      current_bottom_main_grey_background_class: "",
      bottom_main_grey_background_class: {
        "-bottom_main_grey_background_class_not_shown bg-grey-light pin-b absolute h-0 opacity-0 w-full flex justify-center": true
      },
      bottom_main_grey_background_class_slide_up: {
        "-bottom_main_grey_background_class_slide_up bg-grey-light p-2 pin-b absolute h-24 w-full flex justify-center": true
      },
      //2. main grey background at start marking
      bottom_main_grey_background_class_start_marking_class_fullscreen: {
        "-bottom_main_grey_background_class_start_marking_class_fullscreen bg-grey-light p-2 pin-b absolute h-full w-full flex justify-center": true
      },

      //bottom green at default
      current_bottom_green_background_class: "",
      bottom_green_background_class: {
        "-bottom_green_background_class border rounded p-4 bg-green-lightest flex w-5/6": true
      },
      //bottom green at start uploading
      bottom_green_background_start_uploading_class: {
        "-bottom_green_background_start_uploading_class animateAll relative h-full justify-left border rounded p-4 bg-green-lightest flex w-3/4": true
      },
      //bottom green at start marking
      bottom_green_background_class_start_marking_class: {
        "-bottom_green_background_class_start_marking_class animateAll relative h-full justify-center border rounded p-4 bg-green-lightest flex w-3/4": true
      },

      //progress holder at default
      current_main_holdin_container_class: "",
      main_holdin_container_class: {
        "-main_holdin_container_class rounded self-center text-grey-dark relative overflow-hidden w-auto ml-16 p-2 h-34": true
      },
      //progress holder at start marking
      main_holdin_container_start_marking_class: {
        "-main_holdin_container_start_marking_class rounded self-center text-grey-dark relative overflow-hidden w-full p-2 h-full": true
      },

      //main holding container at default
      current_progress_control_container_class: "",
      progress_control_container_class: {
        "-ml-2 mt-6 w-full h-32 block pin-x pin-y self-center text-center relative progress-control-container": true
      },
      progress_control_container_start_marking_class: {
        "-ml-2 mt-6 w-32 h-32 block pin-x pin-y self-center text-center relative progress-control-container": true
      },

      //progress text at default
      current_progress_text_class: "",
      progress_text_class: {
        // "btn" : true
      },
      progress_text_start_marking_class: {
        "-progress_text_start_marking_class serverStatusText flex justify-center text-center inline-block self-center w-full h-10 asolute overflow-hidden": true
      },

      current_spanprogresstext_class: "",
      spanprogresstext_class: {
        "-spanprogresstext_class inline-block self-center relatvie tracking-wide text-xs p-2 font-bold text-blue-darker border rounded": true
      },
      spanprogresstext_start_marking_class: {
        "-spanprogresstext_start_marking_class inline-block self-center relatvie tracking-wide text-xs bg-red-light p-2 font-bold text-white rounded": true
      }

      /******** E N D  C S S    C L A S S E S ********/
    };
  },
  watch: {
    "addData.show"(show) {
      if (show) {
        this.addData.name = "";
        this.addData.type = "";
        this.addData.content = "";
      }
    }
  },
  created: function() {
    this.current_main_holdin_container_class = this.main_holdin_container_class;
    this.current_progress_control_container_class = this.progress_control_container_class;
    this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class;
    this.current_bottom_green_background_class = this.bottom_green_background_start_uploading_class;
    this.current_progress_text_class = this.progress_text_class;
    this.current_spanprogresstext_class = this.spanprogresstext_class;
  },
  methods: {
    updateServerStatus: function(message) {
      this.serverStatusText = message;
    },
    alert(message, alert_type = 3, title = null) {
      // alert2({message})
      let types = ["warning", "error", "success", "info", "question"];
      if (title == null) {
        //let it correspond to the types
        title = types.length > alert_type ? types[alert_type] : "Information";
        title = `${title[0].toUpperCase()}${title.substr(1)}`;
      }
      Swal.fire(
        title,
        String(message),
        alert_type > types.length ? 0 : types[alert_type]
      );
    },

    _allUploadsCompleted: function() {
      //slide up botom panel to main focus
      //show progresscircle
      //write stuff to status update
      this.realMarkingStartedSoShowProgressInFullscreen = true;
      this.serverStatusText =
        "All uploads successful. Now marking the scripts..."; //to trigger display of greenworkingbackground

      this.$refs.uploader.showLoadingMode = true;
      this.showQuotes = true;
      this.$refs.uploader.showFinalStatus = false;

      //buttons
      this.showStartUpoadButton = false;
      this.showStartAllOver = false;
      this.showStopUpoadButton = false;

      this.current_main_holdin_container_class = this.main_holdin_container_start_marking_class;
      this.current_progress_text_class = this.progress_text_start_marking_class;
      this.current_spanprogresstext_class = this.spanprogresstext_start_marking_class;

      this.$refs.uploader.displayLoadingImage = false;
      this.current_bottom_green_background_class = this.bottom_green_background_class_start_marking_class;
      this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class_start_marking_class_fullscreen;
    },

    _showErrorThatTerminatesOngoingMarking: function(mssg) {
      this.alert(mssg, 1);

      //slide up botom panel to main focus
      //show progresscircle
      //write stuff to status update
      this.realMarkingStartedSoShowProgressInFullscreen = false;
      //empty server status text so to the green background is not rendered (watchers!)
      this.serverStatusText = null;
      // this.serverStatusText = "An error occured";//to trigger display of greenworkingbackground

      //buttons
      this.showStartUpoadButton = true;
      this.showStartAllOver = true;
      this.showStopUpoadButton = false;

      this.$refs.uploader.displayLoadingImage = false;
      this.showQuotes = false;

      this.current_bottom_green_background_class = this.bottom_green_background_start_uploading_class;
      this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class_slide_up;
      this.$refs.uploader.recentMarkingSessionCompletedSuccessfully = false;
    },

    _markingCompletedSuccessfully: function() {
      this.startMimickingServerSentStatus = false;
      this.$refs.uploader.showLoadingMode = false;
      this.$refs.uploader.recentMarkingSessionCompletedSuccessfully = true;
      this.$refs.uploader.showFinalStatus = true;
      this.showQuotes = false;
      this.serverStatusText = "Marking completed successfully";
      this.showStartAllOver = true;
    },

    _startAllOver: function() {
      //means we have already uploaded some stuff. We want to redo it
      //we need to reset stuff too
      if (this.$refs.uploader) {
        this.$refs.uploader.ongoingServerPollTimestamp = null;
        this.$refs.uploader.files = [];
      }

      this.serverStatusText = null;
      this.$refs.uploader.commandedServerToStartMarking = false;

    },

    doUITransition: function(status) {
      /*
        //loader img disappears
        //the light green background enlarges to fill the screen
        //red colored progress stuff becomes the progress bar
        //quotes appear underneath the progressbar
        //center of progressbar shows percentage completion
        //at anytime that error occurs along the way, clear evrrything and use Swal to handle it
        */
      switch (status) {
        case "All Uploads Successful":
          this._allUploadsCompleted();
          break;

        case "Show error":
          this._showErrorThatTerminatesOngoingMarking();
          break;

        case "Marking completed successfully":
          this._markingCompletedSuccessfully();
          break;

        case "Start All Over":
          break;
      }
    },

    change_status: function(event) {
      this.status = event.target.parentElement.innerText.trim();
      this.doUITransition(this.status);
    },

    files_change: function(_files) {
      if (_files.length > 0) {
        //if server file stuff is ongoing, show full mode
        if (!this.realMarkingStartedSoShowProgressInFullscreen) {
          this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class_slide_up;
        } else {
          //else if there was a recently concluded marking session, show the ready for upload mode
          if (this.$refs.uploader.recentMarkingSessionCompletedSuccessfully) {
            //only shorten this if and only if no upload is ongoing...it is funny that the custom Vue file uploader component module fires file change anyhow, anytime, randomy arbitrarily
            //So ensure, specifically, that no marking is ongoing
            //All these also ensures that we do not polute the UI - we are ensuring that we keep the UI as-is
            if (this.$refs.uploader.isCurrentlyUploading) {
              console.log("is currently uploading");
              this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class_start_marking_class_fullscreen;
            } else {
              console.log("start fresh upload");
              this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class_slide_up;
            }
          }
        }
      } else {
        this.current_bottom_main_grey_background_class = this.bottom_main_grey_background_class;
      }
    },
    toggleGeneratingLoremIpsum: function() {
      this.startMimickingServerSentStatus = !this
        .startMimickingServerSentStatus;
      if (this.startMimickingServerSentStatus) {
        var vue_instance = this;
        this.mimickeryIntervalReference = setInterval(function() {
          //at this interval's run, recheck if thre are changes to the token so we do not output when we ought not to
          if (vue_instance.startMimickingServerSentStatus) {
            vue_instance.serverStatusText = loremIpsum({
              count: 1, // Number of words, sentences, or paragraphs to generate.
              units: "sentences", // Generate words, sentences, or paragraphs.
              sentenceLowerBound: 2, // Minimum words per sentence.
              sentenceUpperBound: (Math.random() + 0.1) * 10 + 2, // Maximum words per sentence.
              format: "plain" // Plain text or html
            });
          }
        }, 2000);
        console.log(vue_instance.serverStatusText);
      } else {
        //clear any existing interval
        if (this.mimickeryIntervalReference) {
          clearInterval(this.mimickeryIntervalReference);
        }
      }
    }
  }
};
</script>
