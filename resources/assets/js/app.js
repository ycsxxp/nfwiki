import App from './App.vue'
import Vuex from 'vuex' // 引入vuex
import iView from 'iview'; // 引入iView
import 'iview/dist/styles/iview.css';    // 使用 CSS
import router from './router'
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

// 使用vuex
Vue.use(Vuex);
// 使用iView
Vue.use(iView);
Vue.prototype.$http = axios;
/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

// Vue.component('example', require('./components/Example.vue'));
// Vue.component('login', require('./components/Login.vue'));

const store = new Vuex.Store({
	state: {
		loginStatus: false
	},
	mutations: {
		loginSuccess (state) {
			state.loginStatus = true
		},
		logoutSuccess (state) {
			state.loginStatus = false
		}
	}
})

const app = new Vue({
    el: '#app',
    store,
    router,
    template: '<App />',
    components: { App }
});
