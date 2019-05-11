import Vue from 'vue'
import App from './App.vue'

// import './assets/css/bootstrap.min.css'
// import './assets/css/cropper.css'
import './assets/css/font-awesome.min.css'
import './assets/css/progressbar.css'
import './assets/css/tailwind.css'
import './assets/css/app.css'
import 'animate.css'

Vue.config.productionTip = true

new Vue({
  render: h => h(App),
}).$mount('#app')
