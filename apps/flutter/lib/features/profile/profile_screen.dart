import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/i18n/locale_provider.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    final currency = ref.watch(currencyProvider);
    final tr = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(title: Text(tr('profile'))),
      body: ListView(
        children: [
          const SizedBox(height: 24),
          const CircleAvatar(radius: 40, child: Icon(Icons.person, size: 40)),
          const SizedBox(height: 12),
          Text(tr('welcome'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 18)),
          const SizedBox(height: 24),
          ListTile(leading: const Icon(Icons.shopping_bag), title: Text(tr('orders')), onTap: () => context.push('/orders')),
          ListTile(leading: const Icon(Icons.favorite_outline), title: const Text('Wishlist'), onTap: () {}),
          ListTile(leading: const Icon(Icons.location_on_outlined), title: Text(tr('default_address')), onTap: () => context.push('/addresses')),
          ListTile(leading: const Icon(Icons.card_giftcard), title: const Text('Gift Cards'), onTap: () {}),
          ListTile(leading: const Icon(Icons.share), title: const Text('Affiliate Program'), onTap: () {}),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.language),
            title: Text(tr('language')),
            trailing: Text(localeLabels[localeToString(locale)] ?? locale.languageCode),
            onTap: () => _showPicker(context, ref, 'language'),
          ),
          ListTile(
            leading: const Icon(Icons.attach_money),
            title: Text(tr('currency')),
            trailing: Text(currency),
            onTap: () => _showPicker(context, ref, 'currency'),
          ),
          ListTile(leading: const Icon(Icons.privacy_tip_outlined), title: Text(tr('settings')), onTap: () {}),
          const Divider(),
          ListTile(leading: const Icon(Icons.logout), title: Text(tr('logout')), onTap: () => context.push('/login')),
        ],
      ),
    );
  }

  void _showPicker(BuildContext context, WidgetRef ref, String type) {
    showDialog(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: Text(type == 'language' ? localeLabels[localeToString(ref.read(localeProvider))] ?? '' : supportedCurrencies.join(', ')),
        children: type == 'language'
            ? supportedLocales.map((loc) => RadioListTile<Locale>(
                title: Text(localeLabels[localeToString(loc)] ?? ''),
                value: loc, groupValue: ref.read(localeProvider),
                onChanged: (v) { if (v != null) { ref.read(localeProvider.notifier).setLocale(v); Navigator.pop(ctx); } },
              )).toList()
            : supportedCurrencies.map((code) => RadioListTile<String>(
                title: Text(code), value: code, groupValue: ref.read(currencyProvider),
                onChanged: (v) { if (v != null) { ref.read(currencyProvider.notifier).setCurrency(v); Navigator.pop(ctx); } },
              )).toList(),
      ),
    );
  }
}
