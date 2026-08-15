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
  final _posterAnswerCtrl = TextEditingController();
  bool _loading = false;
  String _posterQuestion = '';
  String _posterToken = '';
  bool _posterVerified = false;

  @override
  void initState() {
    super.initState();
    _loadPoster();
  }

  Future<void> _loadPoster() async {
    // 注册路由受 PosterVerify 保护，先获取人机验证题目
    final res = await ApiClient.instance.get('/poster/challenge');
    if (res.isSuccess && res.data != null && mounted) {
      final p = res.data as Map<String, dynamic>;
      setState(() {
        _posterQuestion = p['question'] as String? ?? '';
        _posterToken = p['token'] as String? ?? '';
      });
    }
  }

  Future<void> _verifyPoster() async {
    if (_posterToken.isEmpty || _posterAnswerCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('请输入验证答案')));
      return;
    }
    final res = await ApiClient.instance.post('/poster/verify',
        data: {'token': _posterToken, 'answer': _posterAnswerCtrl.text.trim()});
    if (mounted) {
      if (res.isSuccess && res.data != null) {
        final d = res.data as Map<String, dynamic>;
        setState(() {
          _posterToken = d['token'] as String? ?? '';
          _posterVerified = true;
        });
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('验证通过')));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
        _posterAnswerCtrl.clear();
        _loadPoster();
      }
    }
  }

  Future<void> _register() async {
    if (_emailCtrl.text.isEmpty || _passCtrl.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('请填写邮箱和密码')));
      return;
    }
    if (!_posterVerified || _posterToken.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('请先完成人机验证')));
      return;
    }
    setState(() => _loading = true);
    final res = await ApiClient.instance.post('/auth/register', data: {
      'email': _emailCtrl.text, 'password': _passCtrl.text, 'nickname': _nickCtrl.text,
    }, headers: {'X-Poster-Token': _posterToken});
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
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: Text(_posterQuestion, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold))),
          Expanded(
            child: TextField(
              controller: _posterAnswerCtrl,
              enabled: !_posterVerified,
              decoration: const InputDecoration(labelText: 'Answer', isDense: true),
            ),
          ),
          FilledButton(
            onPressed: _posterVerified ? null : _verifyPoster,
            child: Text(_posterVerified ? 'Verified' : 'Verify'),
          ),
        ]),
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
