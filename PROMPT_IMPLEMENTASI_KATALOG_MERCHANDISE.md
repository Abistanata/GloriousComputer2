# Prompt Implementasi: Katalog Produk Merchandise (Peduli Lingkungan)

Gunakan prompt berikut untuk mengimplementasikan fitur katalog produk merchandise (kaos, mug, dll) di project Laravel **Peduli Lingkungan**. Project target sudah memiliki: admin panel di `/admin/*` dengan middleware is-admin, resource controller pattern, Blade templating, dan upload gambar.

---

## Konteks yang Diabaikan
Jangan implementasikan: **kategori produk**, **supplier**, **metode pengiriman**. Fokus hanya pada produk, tampilan katalog, dan tombol WhatsApp inquiry.

---

## 1. CRUD Produk (Admin)

### Model `Product`
- **Fillable (tanpa category_id, supplier_id, shipping_info):**  
  `name`, `sku`, `description`, `purchase_price`, `selling_price`, `discount_price`, `image`, `current_stock`, `min_stock`, `max_stock`, `condition`, `warranty`, `unit`.
- **Default:** `current_stock` = 0, `min_stock` = 0, `discount_price` = 0, `condition` = 'new', `unit` = 'pcs'.
- **Casts:** harga decimal:2; current_stock, min_stock, max_stock integer.
- **Appends (accessor):** `stock_status`, `has_discount`, `discount_percentage`, `final_price`, formatted prices (formatted_selling_price, formatted_discount_price, dll sesuai kebutuhan).
- **SoftDeletes:** pakai SoftDeletes.
- **Relasi:** tidak wajib category/supplier; jika ada ProductAttribute/Review bisa tetap, tapi bukan syarat.

**Validasi create:**
- `name` required|string|max:255  
- `sku` required|string|max:100|unique:products  
- `description` nullable|string  
- `purchase_price` required|numeric|min:0  
- `selling_price` required|numeric|min:0|gte:purchase_price  
- `discount_price` nullable|numeric|min:0|lte:selling_price  
- `current_stock` nullable|integer|min:0  
- `min_stock` required|integer|min:0  
- `max_stock` nullable|integer|min:0 (jika diisi: max_stock >= current_stock dan max_stock >= min_stock)  
- `condition` required|in:new,used,refurbished  
- `warranty` nullable|in:no_warranty,1_month,3_months,6_months,1_year,2_years,lifetime  
- `unit` required|string|max:20  
- `image` nullable|image|mimes:jpeg,png,jpg,gif|max:2048  

**Validasi update:** sama seperti create, dengan `sku` unique:products,sku,{id}; tambah `remove_image` nullable|boolean.

**Penyimpanan gambar:**
- Disk: `Storage::disk('public')`.  
- Path: `product_images` (simpan dengan `$request->file('image')->store('product_images', 'public')`).  
- Create: jika ada file, simpan path ke `image`.  
- Update: jika ada file baru, hapus file lama `Storage::disk('public')->delete($product->image)` lalu simpan yang baru; jika `remove_image` true, hapus file dan set `image` = null.  
- Delete (soft/hard): hapus file dari storage jika `$product->image` ada.

Admin controller: index (list + optional filter search, condition, has_discount, sort), create, store, show, edit, update, confirm delete, destroy. Route di bawah prefix `admin` dengan middleware auth + is-admin, resource-style: index, create, store, show, edit, update, delete (confirm), destroy.

---

## 2. Tampilan Produk (User / Katalog)

### Katalog (list)
- Route: GET `/products` (dan optional `/products/search` atau query params).
- Controller: tampilkan produk dengan paginate (misal 12 per halaman).
- Filter/pencarian (query params):
  - `search`: cari di `name`, `sku`, `description` (like %search%).
  - `in_stock`: hanya produk dengan `current_stock > 0`.
  - `on_sale`: hanya yang punya diskon (discount_price > 0 dan discount_price < selling_price).
  - `sort`: name_asc, name_desc, price_asc, price_desc, latest, oldest (orderBy name/created_at/selling_price).
- View: grid kartu produk (nama, harga, gambar, link ke detail, tombol WhatsApp).

### Detail produk
- Route: GET `/products/{id}`.
- Controller: Product::findOrFail, pass ke view (tampilkan nama, deskripsi, harga, stok, kondisi, garansi, gambar).
- View: satu halaman detail dengan tombol "Pesan / Tanya via WhatsApp".

---

## 3. Fitur "Pesan via WhatsApp"

### Sumber nomor WhatsApp
- Simpan di config dan env: `config('app.wa_admin')` dari `env('ADMIN_WHATSAPP_NUMBER', '62xxxxxxxxxx')`.
- Di `config/app.php`: `'wa_admin' => env('ADMIN_WHATSAPP_NUMBER', '62xxxxxxxxxx')`.
- Di `.env.example`: `ADMIN_WHATSAPP_NUMBER=62xxxxxxxxxx`.

### Tombol WhatsApp
- Di halaman katalog (setiap kartu produk) dan halaman detail produk: tombol/link "Pesan via WhatsApp" atau "Tanya via WhatsApp".
- Jika tidak butuh login: langsung link `https://wa.me/{nomor}?text={encoded_message}`.
- Jika butuh login: bisa redirect ke login dengan pesan "Silakan login untuk memesan via WhatsApp", atau tetap link wa.me dengan message inquiry (pilih salah satu sesuai kebutuhan project).

### Format pesan (inquiry, tanpa order/cart)
Generate teks pesan di controller (helper method), lalu encode untuk URL:

```
Halo, saya tertarik dengan produk berikut:

📦 *Produk:* {nama_produk}
🏷️ *SKU:* {sku}
💰 *Harga:* Rp {harga_format}
{Diskon: X% (Hemat Rp Y) dan coret harga lama — jika ada diskon}
📊 *Stok:* {stok} unit tersedia / Habis
🔧 *Kondisi:* {kondisi}
✅ *Garansi:* {garansi}

Apakah produk ini masih tersedia? Saya ingin order.
```

- Nomor: hanya digit, format 62xxx (normalize dari config: hapus non-digit, jika diawali 0 ganti jadi 62, jika belum 62 tambahkan 62).
- Link: `https://wa.me/' . $nomor . '?text=' . rawurlencode($message)`.

---

## 4. Ringkasan Teknis untuk Developer

| Aspek | Spesifikasi |
|-------|-------------|
| **Product fields** | name, sku, description, purchase_price, selling_price, discount_price, image, current_stock, min_stock, max_stock, condition, warranty, unit (tanpa category_id, supplier_id, shipping_info) |
| **Gambar** | Simpan di `storage/app/public/product_images`, validasi image|mimes:jpeg,png,jpg,gif|max:2048, hapus file saat update (ganti/hapus) dan saat delete produk |
| **Admin** | Resource controller di `/admin/products`, middleware is-admin, Blade form create/edit dengan enctype multipart/form-data |
| **User** | GET /products (katalog + search, in_stock, on_sale, sort), GET /products/{id} (detail) |
| **WhatsApp** | Config `app.wa_admin`, env `ADMIN_WHATSAPP_NUMBER`; tombol buka wa.me dengan pesan inquiry terisi; tidak wajib fitur order/cart |

---

## 5. Prompt Singkat untuk Dieksekusi (Copy-Paste)

**"Implementasikan katalog produk merchandise di Laravel project ini (Peduli Lingkungan) dengan ketentuan berikut.**

**Admin (di bawah /admin dengan middleware is-admin):**  
- CRUD Produk (resource controller): list, create, store, show, edit, update, confirm delete, destroy.  
- Field produk: name, sku, description, purchase_price, selling_price, discount_price, image, current_stock, min_stock, max_stock, condition (new/used/refurbished), warranty (no_warranty, 1_month, 3_months, 6_months, 1_year, 2_years, lifetime), unit. Tanpa category, supplier, dan shipping.  
- Validasi create/update seperti di atas; image nullable, image|mimes:jpeg,png,jpg,gif|max:2048; update support replace/remove image.  
- Simpan gambar di Storage::disk('public') path product_images; hapus file saat ganti/hapus gambar dan saat delete produk.  
- Model Product: fillable, casts, SoftDeletes, accessor untuk stock_status, has_discount, final_price, formatted prices.

**User:**  
- Halaman katalog GET /products: grid produk, paginate 12; filter/pencarian: search (name/sku/description), in_stock, on_sale, sort (name_asc/desc, price_asc/desc, latest, oldest).  
- Halaman detail GET /products/{id}: tampilkan semua info produk dan tombol 'Pesan via WhatsApp'.

**WhatsApp:**  
- Nomor admin dari config/app.php key wa_admin dan env ADMIN_WHATSAPP_NUMBER (format 62xxx, normalize digit).  
- Tombol 'Pesan via WhatsApp' di katalog (per item) dan di detail produk: link ke wa.me/{nomor}?text={encoded_message}.  
- Format pesan: salam, nama produk, SKU, harga (dan diskon jika ada), stok, kondisi, garansi, kalimat 'Apakah produk ini masih tersedia? Saya ingin order.'  
- Tidak perlu fitur order/cart; hanya inquiry via WhatsApp.**

---

*Dokumen ini dihasilkan dari analisis aplikasi e-commerce Laravel (App2); fitur category, supplier, dan metode pengiriman sengaja diabaikan sesuai permintaan.*
