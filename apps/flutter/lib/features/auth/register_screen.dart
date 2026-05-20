import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/api/api_client.dart';
import '../../core/auth/auth_service.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  final _nickCtrl = TextEditingController();
  bool _loading = false;

  Future<void> _register() async {
    if (_emailCtrl.text.isEmpty || _passCtrl.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('请填写邮箱和密码')));
      return;
    }
    setState(() => _loading = true);
    final res = await ApiClient.instance.post('/auth/register', data: {
      'email': _emailCtrl.text, 'password': _passCtrl.text, 'nickname': _nickCtrl.text,
    });
    if (mounted) {
      setState(() => _loading = false);
      if (res.isSuccess && res.data != null) {
        final d = res.data as Map<String, dynamic>;
        await AuthService.instance.saveTokens(d['access_token'] ?? '', d['refresh_token'] ?? '');
        context.go('/');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Create Account')),
    body: Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24), child: Column(
      mainAxisAlignment: MainAxisAlignment.center, children: [
        const Text('Register', style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
        const SizedBox(height: 32),
        TextField(controller: _nickCtrl, decoration: const InputDecoration(labelText: 'Nickname', border: OutlineInputBorder())),
        const SizedBox(height: 16),
        TextField(controller: _emailCtrl, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
        const SizedBox(height: 16),
        TextField(controller: _passCtrl, obscureText: true, decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder())),
        const SizedBox(height: 24),
        SizedBox(width: double.infinity, child: FilledButton(onPressed: _loading ? null : _register, child: _loading ? const CircularProgressIndicator() : const Text('Create Account'))),
        const SizedBox(height: 12),
        TextButton(onPressed: () => context.go('/login'), child: const Text('Already have an account? Sign In')),
      ],
    ))),
  );

  @override
  void dispose() { _emailCtrl.dispose(); _passCtrl.dispose(); _nickCtrl.dispose(); super.dispose(); }
}
