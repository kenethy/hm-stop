<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Service;
use App\Policies\ResourcePolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $recordTitleAttribute = 'customer_name';

    protected static ?string $navigationGroup = 'Servis & Booking';

    protected static ?int $navigationSort = 1;

    public static function getPolicy(): string
    {
        return ResourcePolicy::class;
    }

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'Servis Kendaraan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pelanggan')
                    ->schema([
                        Forms\Components\Select::make('booking_id')
                            ->label('Booking Terkait')
                            ->options(function () {
                                return Booking::where('status', '!=', 'cancelled')
                                    ->whereNotNull('name')
                                    ->where('name', '!=', '')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $booking = Booking::find($state);
                                    if ($booking) {
                                        $set('customer_name', $booking->name);
                                        $set('phone', $booking->phone);
                                        $set('car_model', $booking->car_model);
                                        $set('service_type', $booking->service_type);

                                        // Check if customer exists with this phone number
                                        $customer = Customer::where('phone', $booking->phone)->first();
                                        if ($customer) {
                                            $set('customer_id', $customer->id);
                                        }
                                    }
                                }
                            })
                            ->nullable(),

                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->options(function () {
                                return Customer::whereNotNull('name')
                                    ->where('name', '!=', '')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        $set('customer_name', $customer->name);
                                        $set('phone', $customer->phone);

                                        // Reset vehicle_id when customer changes
                                        $set('vehicle_id', null);
                                        $set('car_model', null);
                                        $set('license_plate', null);
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Nomor Telepon')
                                    ->required()
                                    ->tel()
                                    ->unique()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->unique()
                                    ->maxLength(255),

                                Forms\Components\Select::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->options([
                                        'male' => 'Laki-laki',
                                        'female' => 'Perempuan',
                                        'other' => 'Lainnya',
                                    ])
                                    ->default('male'),

                                Forms\Components\TextInput::make('city')
                                    ->label('Kota')
                                    ->maxLength(255),
                            ])
                            ->required(),

                        Forms\Components\Select::make('vehicle_id')
                            ->label('Kendaraan')
                            ->options(function (callable $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return [];
                                }

                                return \App\Models\Vehicle::where('customer_id', $customerId)
                                    ->where('is_active', true)
                                    ->get()
                                    ->pluck('full_details', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $vehicle = \App\Models\Vehicle::find($state);
                                    if ($vehicle) {
                                        $set('car_model', $vehicle->model);
                                        $set('license_plate', $vehicle->license_plate);
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('model')
                                    ->label('Model Mobil')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('license_plate')
                                    ->label('Nomor Plat')
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(),

                                Forms\Components\TextInput::make('year')
                                    ->label('Tahun Pembuatan')
                                    ->maxLength(4),

                                Forms\Components\TextInput::make('color')
                                    ->label('Warna')
                                    ->maxLength(50),
                            ])
                            ->hidden(fn(callable $get) => !$get('customer_id')),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nama Pelanggan')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->required()
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('car_model')
                            ->label('Model Mobil')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('license_plate')
                            ->label('Nomor Plat')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Detail Servis')
                    ->schema([
                        Forms\Components\Select::make('service_type')
                            ->label('Jenis Servis')
                            ->options([
                                'Servis Berkala' => 'Servis Berkala',
                                'Tune Up Mesin' => 'Tune Up Mesin',
                                'Servis AC' => 'Servis AC',
                                'Ganti Oli' => 'Ganti Oli',
                                'Perbaikan Rem' => 'Perbaikan Rem',
                                'Balancing' => 'Balancing',
                                'Cuci' => 'Cuci',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->default('Servis Berkala')
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Servis')
                            ->placeholder('Jelaskan detail servis yang dilakukan')
                            ->rows(3),

                        Forms\Components\Textarea::make('parts_used')
                            ->label('Sparepart yang Digunakan')
                            ->placeholder('Daftar sparepart yang digunakan')
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Biaya')
                    ->schema([
                        Forms\Components\TextInput::make('labor_cost')
                            ->label('Biaya Jasa Total')
                            ->helperText('Biaya jasa total akan dibagi rata ke semua montir yang mengerjakan')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                // Recalculate total cost
                                $set('total_cost', $state + $get('parts_cost'));
                            }),

                        Forms\Components\TextInput::make('parts_cost')
                            ->label('Biaya Sparepart')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                // Recalculate total cost
                                $set('total_cost', $get('labor_cost') + $state);
                            }),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Biaya')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(3),

                Forms\Components\Section::make('Montir')
                    ->schema([
                        Forms\Components\Select::make('mechanics')
                            ->label('Montir yang Mengerjakan')
                            ->relationship('mechanics', 'name')
                            ->options(function () {
                                return Mechanic::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->multiple()
                            ->maxItems(2)
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih maksimal 2 montir yang mengerjakan servis ini')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Waktu')
                    ->schema([
                        Forms\Components\DateTimePicker::make('entry_time')
                            ->label('Jam Masuk')
                            ->seconds(false)
                            ->timezone('Asia/Jakarta')
                            ->default(now())
                            ->disabled(fn($record) => $record && $record->entry_time)
                            ->helperText('Otomatis diisi saat servis dibuat'),

                        Forms\Components\DateTimePicker::make('exit_time')
                            ->label('Jam Keluar')
                            ->seconds(false)
                            ->timezone('Asia/Jakarta')
                            ->disabled(fn($record) => $record && $record->status !== 'completed')
                            ->helperText('Otomatis diisi saat servis ditandai selesai'),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Servis')
                            ->options([
                                'in_progress' => 'Dalam Pengerjaan',
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('in_progress')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                if ($state === 'completed') {
                                    // Jika tidak ada montir yang dipilih, tampilkan pesan error
                                    if (empty($get('mechanics'))) {
                                        $set('status', 'in_progress');
                                        Notification::make()
                                            ->title('Montir harus dipilih sebelum menyelesaikan servis')
                                            ->danger()
                                            ->send();
                                    } else {
                                        // Set exit_time jika status completed
                                        $set('exit_time', now());
                                    }
                                }
                            }),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->placeholder('Catatan tambahan tentang servis ini')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Nomor Telepon')
                    ->searchable(),

                Tables\Columns\TextColumn::make('car_model')
                    ->label('Model Mobil')
                    ->searchable(),

                Tables\Columns\TextColumn::make('license_plate')
                    ->label('Nomor Plat')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vehicle.full_details')
                    ->label('Kendaraan')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_type')
                    ->label('Jenis Servis')
                    ->searchable(),

                Tables\Columns\TextColumn::make('mechanics.name')
                    ->label('Montir')
                    ->listWithLineBreaks()
                    ->limitList(2),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'in_progress' => 'Dalam Pengerjaan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diupdate')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('entry_time')
                    ->label('Jam Masuk')
                    ->dateTime('d F Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('exit_time')
                    ->label('Jam Keluar')
                    ->dateTime('d F Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'in_progress' => 'Dalam Pengerjaan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->placeholder('Semua Status')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'license_plate')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('license_plate')
                    ->label('Nomor Plat')
                    ->form([
                        Forms\Components\TextInput::make('license_plate')
                            ->label('Nomor Plat')
                            ->placeholder('Masukkan nomor plat')
                            ->required(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['license_plate'],
                                fn(Builder $query, $licensePlate): Builder => $query->where('license_plate', 'like', "%{$licensePlate}%"),
                            );
                    }),

                Tables\Filters\Filter::make('service_date')
                    ->label('Tanggal Servis')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markAsCompleted')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Service $record) => $record->status === 'in_progress')
                    ->form(function (Service $record) {
                        // Ambil montir yang sudah ada
                        $existingMechanics = $record->mechanics()->pluck('mechanic_id')->toArray();

                        // Ambil biaya jasa total
                        $totalLaborCost = $record->labor_cost;

                        return [
                            Forms\Components\Section::make('Pilih Montir')
                                ->schema([
                                    Forms\Components\Select::make('mechanics')
                                        ->label('Montir yang Mengerjakan')
                                        ->options(function () {
                                            return Mechanic::where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->toArray();
                                        })
                                        ->multiple()
                                        ->maxItems(2)
                                        ->preload()
                                        ->searchable()
                                        ->required()
                                        ->default($existingMechanics)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                                            if (is_array($state)) {
                                                $set('mechanic_costs', array_fill_keys($state, ['labor_cost' => 0]));
                                            }
                                        })
                                        ->helperText('Pilih maksimal 2 montir yang mengerjakan servis ini'),
                                ]),

                            Forms\Components\Section::make('Biaya Jasa per Montir')
                                ->schema([
                                    Forms\Components\Repeater::make('mechanic_costs')
                                        ->label(false)
                                        ->schema([
                                            Forms\Components\Select::make('mechanic_id')
                                                ->label('Montir')
                                                ->options(function () {
                                                    return Mechanic::where('is_active', true)
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->disabled()
                                                ->dehydrated(false),

                                            Forms\Components\TextInput::make('labor_cost')
                                                ->label('Biaya Jasa')
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->default($totalLaborCost)
                                                ->required(),
                                        ])
                                        ->itemLabel(function (array $state): ?string {
                                            return isset($state['mechanic_id']) && $state['mechanic_id'] ? Mechanic::find($state['mechanic_id'])?->name : null;
                                        })
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->dehydrated()
                                        ->columns(2),
                                ])
                                ->visible(fn(Forms\Get $get) => is_array($get('mechanics')) && count($get('mechanics')) > 0),
                        ];
                    })
                    ->action(function (array $data, Service $record) {
                        // Debug: Tampilkan data yang diterima dengan lebih detail
                        Log::info('Mechanics data received for service #' . $record->id . ':', $data);
                        Log::info('Data structure:', ['type' => gettype($data), 'keys' => array_keys($data)]);

                        // Cek apakah ada montir yang sudah terpilih sebelumnya
                        $existingMechanics = $record->mechanics()->pluck('mechanic_id')->toArray();
                        Log::info('Existing mechanics for service #' . $record->id . ':', $existingMechanics);

                        // Validasi montir dengan lebih detail
                        if (empty($data['mechanics']) && empty($existingMechanics)) {
                            Notification::make()
                                ->title('Montir harus dipilih sebelum menyelesaikan servis')
                                ->body('Silakan pilih minimal 1 montir untuk menyelesaikan servis ini.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Gunakan montir yang sudah ada jika tidak ada yang dipilih
                        if (empty($data['mechanics']) && !empty($existingMechanics)) {
                            $data['mechanics'] = $existingMechanics;
                            Log::info('Using existing mechanics:', $existingMechanics);
                        }

                        // Dapatkan tanggal awal dan akhir minggu saat ini (Senin-Minggu)
                        $now = now();
                        $weekStart = $now->copy()->startOfWeek();
                        $weekEnd = $now->copy()->endOfWeek();

                        // Hapus semua relasi montir yang ada
                        $record->mechanics()->detach();

                        // Proses biaya jasa per montir
                        if (isset($data['mechanic_costs']) && is_array($data['mechanic_costs'])) {
                            Log::info('Mechanic costs data:', $data['mechanic_costs']);

                            foreach ($data['mechanic_costs'] as $index => $costData) {
                                if (isset($data['mechanics'][$index]) && isset($costData['labor_cost'])) {
                                    $mechanicId = $data['mechanics'][$index];
                                    $laborCost = $costData['labor_cost'];

                                    Log::info("Setting labor cost for mechanic #{$mechanicId}: {$laborCost}");

                                    // Tambahkan montir dengan biaya jasa yang ditentukan
                                    $record->mechanics()->attach($mechanicId, [
                                        'labor_cost' => $laborCost,
                                        'week_start' => $weekStart,
                                        'week_end' => $weekEnd,
                                    ]);

                                    // Generate atau update laporan mingguan montir
                                    $mechanic = Mechanic::find($mechanicId);
                                    if ($mechanic) {
                                        $mechanic->generateWeeklyReport($weekStart, $weekEnd);
                                    }
                                }
                            }
                        } else {
                            // Fallback ke cara lama jika tidak ada data biaya jasa per montir
                            Log::info('No mechanic costs data, using default labor cost');

                            // Simpan montir yang dipilih dengan biaya jasa default
                            foreach ($data['mechanics'] as $mechanicId) {
                                $record->mechanics()->attach($mechanicId, [
                                    'labor_cost' => $record->labor_cost,
                                    'week_start' => $weekStart,
                                    'week_end' => $weekEnd,
                                ]);

                                // Generate atau update laporan mingguan montir
                                $mechanic = Mechanic::find($mechanicId);
                                if ($mechanic) {
                                    $mechanic->generateWeeklyReport($weekStart, $weekEnd);
                                }
                            }
                        }

                        // Update status servis
                        $record->status = 'completed';
                        $record->completed_at = now();
                        $record->exit_time = now();
                        $record->save();

                        Notification::make()
                            ->title('Servis telah ditandai sebagai selesai')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('sendFollowUpWhatsApp')
                    ->label('Kirim Follow-up')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn(Service $record) => $record->status === 'completed')
                    ->form([
                        Forms\Components\Select::make('template')
                            ->label('Template Pesan')
                            ->options([
                                'follow_up' => 'Follow-up Standar',
                                'feedback' => 'Minta Feedback',
                                'promo' => 'Tawarkan Promo Berikutnya',
                            ])
                            ->default('follow_up')
                            ->selectablePlaceholder(false)
                            ->required(),
                        Forms\Components\Textarea::make('custom_message')
                            ->label('Pesan Tambahan (Opsional)')
                            ->placeholder('Tambahkan pesan khusus di sini (opsional)')
                            ->rows(3),
                    ])
                    ->action(function (array $data, Service $record): void {
                        // Format nomor telepon untuk WhatsApp
                        $phone = preg_replace('/[^0-9]/', '', $record->phone);
                        if (substr($phone, 0, 1) === '0') {
                            $phone = '62' . substr($phone, 1);
                        } elseif (substr($phone, 0, 2) !== '62') {
                            $phone = '62' . $phone;
                        }

                        // Buat pesan berdasarkan template yang dipilih
                        $message = match ($data['template']) {
                            'follow_up' => "Halo {$record->customer_name},\n\n" .
                                "Terima kasih telah mempercayakan kendaraan Anda kepada Hartono Motor. " .
                                "Servis {$record->service_type} untuk mobil {$record->car_model} Anda telah selesai.\n\n" .
                                "Bagaimana kondisi kendaraan Anda setelah servis? Apakah ada masalah atau pertanyaan yang ingin Anda sampaikan?\n\n" .
                                "Kami sangat menghargai umpan balik Anda untuk meningkatkan layanan kami.\n\n" .
                                "Terima kasih,\nTim Hartono Motor",

                            'feedback' => "Halo {$record->customer_name},\n\n" .
                                "Terima kasih telah mempercayakan servis {$record->service_type} untuk mobil {$record->car_model} Anda kepada Hartono Motor.\n\n" .
                                "Kami ingin mengetahui pendapat Anda tentang layanan kami. Mohon berikan penilaian Anda dengan membalas pesan ini dengan angka 1-5 (1: Sangat Tidak Puas, 5: Sangat Puas).\n\n" .
                                "Kami juga sangat menghargai saran dan masukan Anda untuk meningkatkan kualitas layanan kami.\n\n" .
                                "Terima kasih,\nTim Hartono Motor",

                            'promo' => "Halo {$record->customer_name},\n\n" .
                                "Terima kasih telah mempercayakan servis {$record->service_type} untuk mobil {$record->car_model} Anda kepada Hartono Motor.\n\n" .
                                "Sebagai pelanggan setia kami, Anda berhak mendapatkan DISKON 10% untuk servis berikutnya dalam 3 bulan ke depan.\n\n" .
                                "Gunakan kode promo: HARTONO10\n\n" .
                                "Jangan lewatkan kesempatan ini untuk merawat kendaraan Anda dengan harga spesial!\n\n" .
                                "Terima kasih,\nTim Hartono Motor",

                            default => "Halo {$record->customer_name},\n\n" .
                                "Terima kasih telah mempercayakan kendaraan Anda kepada Hartono Motor.\n\n" .
                                "Terima kasih,\nTim Hartono Motor",
                        };

                        // Tambahkan pesan kustom jika ada
                        if (!empty($data['custom_message'])) {
                            $message .= "\n\n" . $data['custom_message'];
                        }

                        // Encode pesan untuk URL
                        $encodedMessage = urlencode($message);

                        // Buat URL WhatsApp
                        $whatsappUrl = "https://wa.me/{$phone}?text={$encodedMessage}";

                        // Tampilkan notifikasi sukses
                        Notification::make()
                            ->title('Pesan follow-up siap dikirim')
                            ->body('WhatsApp akan terbuka dengan pesan yang sudah disiapkan.')
                            ->success()
                            ->send();

                        // Redirect ke URL WhatsApp
                        redirect()->away($whatsappUrl);
                    }),
                Tables\Actions\Action::make('markAsCancelled')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Service $record) => $record->status === 'in_progress')
                    ->requiresConfirmation()
                    ->action(function (Service $record) {
                        $record->status = 'cancelled';
                        $record->save();

                        Notification::make()
                            ->title('Servis telah dibatalkan')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('sendWhatsApp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(function (Service $record) {
                        // Format the phone number
                        $phone = preg_replace('/[^0-9]/', '', $record->phone);

                        // If the number starts with 0, replace it with 62
                        if (substr($phone, 0, 1) === '0') {
                            $phone = '62' . substr($phone, 1);
                        }
                        // If the number doesn't start with 62, add it
                        elseif (substr($phone, 0, 2) !== '62') {
                            $phone = '62' . $phone;
                        }

                        // Create the message based on service status
                        $message = match ($record->status) {
                            'in_progress' => "Halo {$record->customer_name},\n\nMobil Anda ({$record->car_model}) sedang dalam proses servis di Hartono Motor.\n\nJenis Servis: {$record->service_type}\n\nKami akan menghubungi Anda kembali ketika servis telah selesai.\n\nTerima kasih,\nTim Hartono Motor",

                            'completed' => "Halo {$record->customer_name},\n\nServis mobil Anda ({$record->car_model}) di Hartono Motor telah SELESAI.\n\nJenis Servis: {$record->service_type}\n\nTotal Biaya: Rp " . number_format($record->total_cost, 0, ',', '.') . "\n\nMobil Anda sudah siap untuk diambil. Terima kasih telah mempercayakan kendaraan Anda pada kami.\n\nSalam,\nTim Hartono Motor",

                            'cancelled' => "Halo {$record->customer_name},\n\nKami ingin menginformasikan bahwa servis mobil Anda ({$record->car_model}) di Hartono Motor telah DIBATALKAN.\n\nJika Anda memiliki pertanyaan, silakan hubungi kami.\n\nTerima kasih,\nTim Hartono Motor",

                            default => "Halo {$record->customer_name},\n\nTerima kasih telah mempercayakan servis mobil Anda ({$record->car_model}) di Hartono Motor.\n\nJika ada pertanyaan, silakan hubungi kami.\n\nTerima kasih,\nTim Hartono Motor",
                        };

                        // URL encode the message
                        $encodedMessage = urlencode($message);

                        // Return the WhatsApp URL
                        return "https://wa.me/{$phone}?text={$encodedMessage}";
                    })
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markAsCompletedBulk')
                        ->label('Tandai Selesai')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('mechanics')
                                ->label('Montir yang Mengerjakan')
                                ->options(function () {
                                    return Mechanic::where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->multiple()
                                ->maxItems(2)
                                ->preload()
                                ->searchable()
                                ->required()
                                ->helperText('Pilih maksimal 2 montir yang mengerjakan servis ini'),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records) {
                            // Debug: Tampilkan data yang diterima dengan lebih detail
                            Log::info('Bulk action mechanics data received:', $data);
                            Log::info('Bulk action data structure:', ['type' => gettype($data), 'keys' => array_keys($data)]);

                            // Validasi montir dengan lebih detail
                            if (empty($data['mechanics'])) {
                                Notification::make()
                                    ->title('Montir harus dipilih sebelum menyelesaikan servis')
                                    ->body('Silakan pilih minimal 1 montir untuk menyelesaikan servis ini.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $records->each(function ($record) use ($data) {
                                if ($record->status === 'in_progress') {
                                    // Simpan montir yang dipilih
                                    $record->mechanics()->sync($data['mechanics']);

                                    // Hitung biaya jasa per montir - setiap montir mendapatkan biaya jasa penuh
                                    // Tidak perlu membagi biaya jasa, setiap montir mendapatkan biaya jasa penuh
                                    $laborCostPerMechanic = $record->labor_cost;

                                    // Dapatkan tanggal awal dan akhir minggu saat ini (Senin-Minggu)
                                    $now = now();
                                    $weekStart = $now->copy()->startOfWeek();
                                    $weekEnd = $now->copy()->endOfWeek();

                                    // Update biaya jasa untuk setiap montir
                                    foreach ($data['mechanics'] as $mechanicId) {
                                        $record->mechanics()->updateExistingPivot($mechanicId, [
                                            'labor_cost' => $laborCostPerMechanic,
                                            'week_start' => $weekStart,
                                            'week_end' => $weekEnd,
                                        ]);

                                        // Generate atau update laporan mingguan montir
                                        $mechanic = Mechanic::find($mechanicId);
                                        if ($mechanic) {
                                            $mechanic->generateWeeklyReport($weekStart, $weekEnd);
                                        }
                                    }

                                    // Update status servis
                                    $record->status = 'completed';
                                    $record->completed_at = now();
                                    $record->exit_time = now();
                                    $record->save();
                                }
                            });

                            Notification::make()
                                ->title('Servis telah ditandai sebagai selesai')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UpdatesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest();
    }

    // Add a hook to calculate the total cost before saving
    public static function beforeSave(Forms\Form $form): void
    {
        $form->getState();

        $form->model->total_cost = $form->model->labor_cost + $form->model->parts_cost;

        // Jika ini adalah record baru, set entry_time ke waktu saat ini
        if (!$form->model->exists && !$form->model->entry_time) {
            $form->model->entry_time = now();
        }

        if ($form->model->status === 'completed') {
            // Jika status completed, pastikan ada montir yang dipilih
            if ($form->model->mechanics()->count() === 0) {
                Notification::make()
                    ->title('Montir harus dipilih sebelum menyelesaikan servis')
                    ->danger()
                    ->send();

                // Kembalikan status ke in_progress
                $form->model->status = 'in_progress';
            } else {
                // Set completed_at dan exit_time jika belum diset
                if (!$form->model->completed_at) {
                    $form->model->completed_at = now();
                }

                if (!$form->model->exit_time) {
                    $form->model->exit_time = now();
                }

                // Hitung biaya jasa per montir - setiap montir mendapatkan biaya jasa penuh
                // Tidak perlu membagi biaya jasa, setiap montir mendapatkan biaya jasa penuh
                $laborCostPerMechanic = $form->model->labor_cost;

                // Dapatkan tanggal awal dan akhir minggu saat ini (Senin-Minggu)
                $now = now();
                $weekStart = $now->copy()->startOfWeek();
                $weekEnd = $now->copy()->endOfWeek();

                // Update biaya jasa untuk setiap montir
                $form->model->mechanics()->each(function ($mechanic) use ($laborCostPerMechanic, $weekStart, $weekEnd) {
                    $mechanic->pivot->labor_cost = $laborCostPerMechanic;
                    $mechanic->pivot->week_start = $weekStart;
                    $mechanic->pivot->week_end = $weekEnd;
                    $mechanic->pivot->save();

                    // Generate atau update laporan mingguan montir
                    $mechanic->generateWeeklyReport($weekStart, $weekEnd);
                });
            }
        }

        // Check if customer_id is not set but we have customer_name and phone
        if (!$form->model->customer_id && $form->model->customer_name && $form->model->phone) {
            // Check if customer exists with this phone number
            $customer = Customer::where('phone', $form->model->phone)->first();

            if ($customer) {
                // If customer exists, associate service with this customer
                $form->model->customer_id = $customer->id;
            } else {
                // If customer doesn't exist, create a new one
                $customer = Customer::create([
                    'name' => $form->model->customer_name,
                    'phone' => $form->model->phone,
                    'is_active' => true,
                ]);

                // Associate service with the new customer
                $form->model->customer_id = $customer->id;
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
