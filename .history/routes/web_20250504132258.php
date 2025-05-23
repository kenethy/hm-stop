<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\SitemapController;

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Services
Route::get('/servis', [ServiceController::class, 'index'])->name('services');

// Spare Parts
Route::get('/spare-parts', [SparePartController::class, 'index'])->name('spare-parts');

// Booking
Route::get('/booking', [BookingController::class, 'index'])->name('booking');
Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');

// About
Route::get('/about', [AboutController::class, 'index'])->name('about');

// Gallery
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
Route::get('/gallery/{slug}', [GalleryController::class, 'show'])->name('gallery.show');

// Blog
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Contact
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Promos
Route::get('/promos', [PromoController::class, 'index'])->name('promos');
Route::get('/promos/{slug}', [PromoController::class, 'show'])->name('promos.show');

// Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap/main', function () {
    $content = view('sitemap.main');
    return response($content, 200)->header('Content-Type', 'text/xml');
});
Route::get('/sitemap/posts', [SitemapController::class, 'posts']);
Route::get('/sitemap/categories', [SitemapController::class, 'categories']);
Route::get('/sitemap/tags', [SitemapController::class, 'tags']);
Route::get('/sitemap/promos', [SitemapController::class, 'promos']);
