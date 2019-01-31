<template>
  <div class="flex relative">
    <div class="w-5/6">
          <div class="bg-grey-lightest rounded fixed w-4/5 pin-y overflow-visible m-5 border">
            <uploader v-if="1" 
              class="bg-grey-lightest" 
              :showStartUpoadButton="showStartUpoadButton"
              :showStopUpoadButton="showStopUpoadButton"
              :showDraggedInFiles="showDraggedInFiles"
              :serverStatusText="serverStatusText"
              :startMarking="startMarking"
              :showServerStatusText="startMimickingServerSentStatus"
            ></uploader>
          </div>
    </div>
    <div class="bg-green-lightest rounded absolute w-1/6 pin-r m-5 min-h-full p-2 border text-xs">
      <label class="block cursor-pointer p-1 mt-1 ">Start Uploading <input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">All Uploads Successful<input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Server Start Checking to esure all files uploaded<input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Show error preventing start marking<input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Start marking <input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Show marking error<input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Marking completed successfully<input name="status[]" type="radio" @click="change_status($event)"> </label>
      <label class="block cursor-pointer p-1 mt-1 ">Start All Over<input name="status[]" type="radio" @click="change_status($event)"> </label>
      
      <label class="block cursor-pointer p-1 mt-1 ">
        Mimick server processing files status messages <input type="checkbox" @click="toggleGeneratingLoremIpsum"> 
      </label>
      
      <div class="p-1 m-1 mt-12 border rounded bg-black text-white" v-html="status"></div>
    </div>
  </div>
</template>

<script>

  import Uploader from './components/Uploader.vue'
  import loremIpsum from 'lorem-ipsum'

  export default {
    components: {
      Uploader
    },
    data() {
      return {
        files: [],
        accept: 'image/png,image/gif,image/jpeg,image/pdf,doc/pdf',
        extensions: 'gif,jpg,jpeg,png,webp,pdf',
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
        name: 'file',
        postAction: '/upload/post',
        // putAction: '/upload/put',
        headers: {
          'X-Csrf-Token': 'xxxx',
        },
        data: {
          '_csrf_token': 'xxxxxx',
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
        status: "...",
        showStartUpoadButton: false,
        showDraggedInFiles: true, //( files.length > 0 )
        showStopUpoadButton: false,
        serverStatusText: '',
        startMarking: false,
        startMimickingServerSentStatus: false,
        mimickeryIntervalReference: null
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
          }
          if (newFile.error && !oldFile.error) {
            // error
          }
          if (newFile.success && !oldFile.success) {
            // success
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
      alert(message) {
        alert(message)
      },
      onEditFileShow(file) {
        this.editFile = { ...file, show: true }
        this.$refs.upload.update(file, { error: 'edit' })
      },
      onEditorFile() {
        if (!this.$refs.upload.features.html5) {
          this.alert('Your browser does not support')
          this.editFile.show = false
          return
        }
        let data = {
          name: this.editFile.name,
        }
        if (this.editFile.cropper) {
          let binStr = atob(this.editFile.cropper.getCroppedCanvas().toDataURL(this.editFile.type).split(',')[1])
          let arr = new Uint8Array(binStr.length)
          for (let i = 0; i < binStr.length; i++) {
            arr[i] = binStr.charCodeAt(i)
          }
          data.file = new File([arr], data.name, { type: this.editFile.type })
          data.size = data.file.size
        }
        this.$refs.upload.update(this.editFile.id, data)
        this.editFile.error = ''
        this.editFile.show = false
      },
      // add folader
      onAddFolader() {
        if (!this.$refs.upload.features.directory) {
          this.alert('Your browser does not support')
          return
        }
        let input = this.$refs.upload.$el.querySelector('input')
        input.directory = true
        input.webkitdirectory = true
        this.directory = true
        input.onclick = null
        input.click()
        // eslint-disable-next-line
        input.onclick = (e) => {
          this.directory = false
          input.directory = false
          input.webkitdirectory = false
        }
      },
      onAddData() {
        this.addData.show = false
        if (!this.$refs.upload.features.html5) {
          this.alert('Your browser does not support')
          return
        }
        let file = new window.File([this.addData.content], this.addData.name, {
          type: this.addData.type,
        })
        this.$refs.upload.add(file)
      },
      change_status:function(event){

        this.status = event.target.parentElement.innerText.trim();

        switch(status){
          case 'Start Sending':
          break;

          case 'Send file completed':
          break;

          case 'Start marking':
            this.startMarking = true;
          break;

          case 'Show marking error':
          break;

          case 'Marking ended':
          break;

          case 'Start All Over':
          break;
        }
      },
    toggleGeneratingLoremIpsum:function(){
      this.startMimickingServerSentStatus = !this.startMimickingServerSentStatus;
      if(this.startMimickingServerSentStatus){
        var vue_instance = this;
        this.mimickeryIntervalReference = setInterval(function(){
          vue_instance.serverStatusText  = loremIpsum({
                            count: 1                      // Number of words, sentences, or paragraphs to generate.
                          , units: 'sentences'            // Generate words, sentences, or paragraphs.
                          , sentenceLowerBound: 2         // Minimum words per sentence.
                          , sentenceUpperBound: ( ( ( Math.random() + 0.1 ) * 10 ) + 2 )        // Maximum words per sentence.
                          , format: 'plain'               // Plain text or html
                        });
        }, 2000 );
        console.log(vue_instance.serverStatusText);
      }else{
        //clear any existing interval
        if(this.mimickeryIntervalReference){
          clearInterval(this.mimickeryIntervalReference);
        }
      }
    }
  }
}
</script>
