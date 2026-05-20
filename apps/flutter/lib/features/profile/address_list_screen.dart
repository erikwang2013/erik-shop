import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';

class AddressListScreen extends StatefulWidget {
  const AddressListScreen({super.key});
  @override
  State<AddressListScreen> createState() => _AddressListScreenState();
}

class _AddressListScreenState extends State<AddressListScreen> {
  List<Map<String, dynamic>> _addresses = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await ApiClient.instance.get('/user/addresses');
    if (mounted && res.isSuccess && res.data != null) {
      setState(() {
        _addresses = List<Map<String, dynamic>>.from(res.data as List);
        _loading = false;
      });
    }
  }

  Future<void> _delete(String id) async {
    await ApiClient.instance.delete('/user/addresses/$id');
    _load();
  }

  Future<void> _setDefault(String id) async {
    await ApiClient.instance.put('/user/addresses/$id', data: {'is_default': 1});
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('收货地址')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showAddressForm(null),
        child: const Icon(Icons.add),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _addresses.isEmpty
              ? const Center(child: Text('暂无地址'))
              : ListView.builder(
                  itemCount: _addresses.length,
                  itemBuilder: (_, i) {
                    final a = _addresses[i];
                    return Card(
                      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      child: ListTile(
                        title: Row(
                          children: [
                            Text(a['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                            if (a['is_default'] == 1)
                              Container(
                                margin: const EdgeInsets.only(left: 8),
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                decoration: BoxDecoration(color: Colors.blue[50], borderRadius: BorderRadius.circular(4)),
                                child: const Text('默认', style: TextStyle(fontSize: 10, color: Colors.blue)),
                              ),
                          ],
                        ),
                        subtitle: Text('${a['phone'] ?? ''}\n${a['province'] ?? ''} ${a['city'] ?? ''} ${a['district'] ?? ''} ${a['detail'] ?? ''}'),
                        trailing: PopupMenuButton<String>(
                          onSelected: (v) {
                            if (v == 'delete') _delete(a['id'] as String);
                            if (v == 'default') _setDefault(a['id'] as String);
                          },
                          itemBuilder: (_) => [
                            const PopupMenuItem(value: 'default', child: Text('设为默认')),
                            const PopupMenuItem(value: 'delete', child: Text('删除', style: TextStyle(color: Colors.red))),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }

  void _showAddressForm(Map<String, dynamic>? addr) {
    final nameCtrl = TextEditingController(text: addr?['name'] ?? '');
    final phoneCtrl = TextEditingController(text: addr?['phone'] ?? '');
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: '收件人')),
            TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: '电话')),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () async {
                await ApiClient.instance.post('/user/addresses', data: {'name': nameCtrl.text, 'phone': phoneCtrl.text});
                if (ctx.mounted) Navigator.pop(ctx);
                _load();
              },
              child: const Text('保存'),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
