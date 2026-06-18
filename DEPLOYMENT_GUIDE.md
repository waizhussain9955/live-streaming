# Production Live TV Backend & Flutter Integration Guide

## 1. Supabase Setup
1. Create a new project at [supabase.com](https://supabase.com).
2. Go to the **SQL Editor** in the left sidebar.
3. Paste the content of `backend/supabase_production.sql` and click **Run**.
4. This will create the `channels` table, indexes, RLS policies, and add sample data.

## 2. Vercel Backend Deployment
1. Create a new repository on GitHub and push the contents of the `backend/` folder.
2. Go to [vercel.com](https://vercel.com) and import the repository.
3. During setup, go to **Environment Variables** and add:
   - `SUPABASE_URL`: (Find in Supabase -> Settings -> API)
   - `SUPABASE_ANON_KEY`: (Find in Supabase -> Settings -> API)
4. Click **Deploy**.
5. Once deployed, you will get a URL like `https://your-project.vercel.app`.

## 3. Testing API Endpoints
- List all channels: `https://your-project.vercel.app/api/channels`
- Filter by category: `https://your-project.vercel.app/api/channels?category=sports`
- List categories: `https://your-project.vercel.app/api/categories`

## 4. Flutter Connection Guide

### Step 1: Add http package
In your `pubspec.yaml`:
```yaml
dependencies:
  http: ^1.2.0
```

### Step 2: Create Data Model
```dart
class ChannelModel {
  final String id;
  final String name;
  final String streamUrl;
  final String? logoUrl;
  final String category;

  ChannelModel({
    required this.id,
    required this.name,
    required this.streamUrl,
    this.logoUrl,
    required this.category,
  });

  factory ChannelModel.fromJson(Map<String, dynamic> json) {
    return ChannelModel(
      id: json['id'],
      name: json['name'],
      streamUrl: json['stream_url'],
      logoUrl: json['logo_url'],
      category: json['category'],
    );
  }
}
```

### Step 3: API Service Example
```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  static const String baseUrl = 'https://your-project.vercel.app/api';

  static Future<List<ChannelModel>> fetchChannels() async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/channels'));
      
      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        if (body['success'] == true) {
          List<dynamic> data = body['data'];
          return data.map((json) => ChannelModel.fromJson(json)).toList();
        }
      }
      return [];
    } catch (e) {
      print('API Error: $e');
      return [];
    }
  }
}
```

## 5. Security & Best Practices
- **No Hardcoding**: All sensitive keys are stored in Vercel Environment Variables.
- **Row Level Security**: Supabase RLS ensures only `is_active = true` channels are visible.
- **Rate Limiting**: Handled by Vercel infrastructure.
- **Input Validation**: API endpoints validate query parameters before querying the database.
