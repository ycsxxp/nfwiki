<style>
.addUserForm {
	width: 350px;
	padding: 20px 0 20px 0;
}
</style>
<template>
	<div class="addUserForm">
		<Form ref="userInfo" :model="userInfo" :label-width="80" :rules="userValidate">
		  <Form-item label="姓名" prop="name">
		    <Input type="text" v-model="userInfo.name" placeholder="请输入姓名" />
		  </Form-item>
		  <Form-item label="帐号" prop="account">
		    <Input type="text" v-model="userInfo.account" placeholder="请输入帐号" />
		  </Form-item>
      <Form-item label="邮箱" prop="email">
        <Input v-model="userInfo.email" placeholder="请输入邮箱"></Input>
      </Form-item>
      <Form-item label="密码" prop="password">
        <Input type="password" v-model="userInfo.password" placeholder="请输入密码"></Input>
      </Form-item>
      <Form-item label="地址" prop="address">
        <Select v-model="userInfo.address" placeholder="请选择所在地">
          <Option value="beijing">北京市</Option>
          <Option value="shanghai">上海市</Option>
          <Option value="shenzhen">深圳市</Option>
        </Select>
        <!-- <Input type="text" v-model="userInfo.address" placeholder="请输入" /> -->
      </Form-item>
		  <Form-item>
		  	<Button type="primary" @click="handleSubmit('userInfo')">提交</Button>
		  	<Button type="ghost" @click="handleReset('userInfo')" style="margin-left: 8px">重置</Button>
      </Form-item>
		</Form>
	</div>
</template>
<script>
export default {
	data () {
	  return {
      type: this.$route.params.type,
      apiurl: '',
	    userInfo: {
	      name: '',
	      account: '',
        address: '',
        email: '',
        password: ''
	    },
	    userValidate: {
	      name: [
	        { required: true, message: '姓名不能为空', trigger: 'blur' }
	      ],
        account: [
          { required: true, message: '不能为空', trigger: 'blur' }
        ],
        email: [
          { required: true, message: '邮箱不能为空', trigger: 'blur' },
          { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }
        ],
        password: [
          { required: true, message: '密码不能为空', trigger: 'blur' }
        ],
        address: [
          { required: true, message: '请选择城市', trigger: 'change' }
        ]
	    }
	  }
  },
  created () {
    if(this.type == 'edit') {
      this.userInfo = this.$store.state.data.userFormData.row
    }
  },
  methods: {
  	handleSubmit (name) {
  		this.$refs[name].validate((valid) => {
  			if (valid) {
          this.userInfo._token = window.Laravel.csrfToken
          if(this.type == 'add') {
            this.apiurl = '/adduser'
          }else {
            this.apiurl = '/updateuser'
          }
          this.$http.post(this.apiurl, this.userInfo).then(
            response => {
              this.$Message.success('添加成功!');
              this.$router.push({ path: '/user' })
            },
            response => {
              this.$Message.error(this.$store.state.responseErrorMsg)
            }
          );
  			} else {
  				this.$Message.error('表单验证失败!');
  			}
	    })
  	},
  	handleReset (name) {
  		this.$refs[name].resetFields();
  	}
  }
}
</script>