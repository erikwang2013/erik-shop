import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: ListView(
        children: [
          const SizedBox(height: 24),
          const CircleAvatar(radius: 40, child: Icon(Icons.person, size: 40)),
          const SizedBox(height: 12),
          const Text('Welcome!', textAlign: TextAlign.center, style: TextStyle(fontSize: 18)),
          const SizedBox(height: 24),
          ListTile(leading: const Icon(Icons.shopping_bag), title: const Text('My Orders'), onTap: () => context.push('/orders')),
          ListTile(leading: const Icon(Icons.favorite_outline), title: const Text('Wishlist'), onTap: () {}),
          ListTile(leading: const Icon(Icons.location_on_outlined), title: const Text('Addresses'), onTap: () {}),
          ListTile(leading: const Icon(Icons.card_giftcard), title: const Text('Gift Cards'), onTap: () {}),
          ListTile(leading: const Icon(Icons.share), title: const Text('Affiliate Program'), onTap: () {}),
          const Divider(),
          ListTile(leading: const Icon(Icons.language), title: const Text('Language'), trailing: const Text('English')),
          ListTile(leading: const Icon(Icons.attach_money), title: const Text('Currency'), trailing: const Text('USD')),
          ListTile(leading: const Icon(Icons.privacy_tip_outlined), title: const Text('Privacy Settings'), onTap: () {}),
          const Divider(),
          ListTile(leading: const Icon(Icons.logout), title: const Text('Sign Out'), onTap: () => context.push('/login')),
        ],
      ),
    );
  }
}
