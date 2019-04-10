import Vue from 'vue'
import App from './App.vue'
import './assets/css/app.css'
import './assets/css/tailwind.css'
import 'animate.css'

Vue.config.productionTip = false

new Vue({
  render: h => h(App),
}).$mount('#app')
