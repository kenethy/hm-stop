#!/bin/bash
# fix-duplicate-method-v2.sh - Script untuk memperbaiki masalah duplicate method (versi 2)

# Warna untuk output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Memulai perbaikan masalah duplicate method (versi 2)...${NC}\n"

# 1. Hentikan semua container
echo -e "${YELLOW}1. Menghentikan semua container...${NC}"
docker-compose down
if [ $? -ne 0 ]; then
  echo -e "   ${RED}✗${NC} Gagal menghentikan semua container"
  echo -e "   ${YELLOW}!${NC} Melanjutkan proses..."
else
  echo -e "   ${GREEN}✓${NC} Semua container berhasil dihentikan"
fi

# 2. Hapus semua file cadangan dengan perintah find
echo -e "\n${YELLOW}2. Menghapus semua file cadangan...${NC}"
find app -type f -name "*.php*" | grep -v ".php$" | xargs rm -f
echo -e "   ${GREEN}✓${NC} Semua file cadangan berhasil dihapus"

# 3. Verifikasi penghapusan
echo -e "\n${YELLOW}3. Memverifikasi penghapusan...${NC}"
REMAINING_FILES=$(find app -type f -name "*.php*" | grep -v ".php$")
if [ -z "$REMAINING_FILES" ]; then
  echo -e "   ${GREEN}✓${NC} Semua file cadangan berhasil dihapus"
else
  echo -e "   ${RED}✗${NC} Masih ada file cadangan yang tersisa:"
  echo "$REMAINING_FILES"
  echo -e "   ${YELLOW}!${NC} Mencoba menghapus file-file tersebut dengan perintah lain..."
  
  for file in $REMAINING_FILES; do
    echo -e "   ${YELLOW}Menghapus${NC} $file dengan perintah lain"
    rm -f "$file"
    if [ $? -ne 0 ]; then
      echo -e "   ${RED}✗${NC} Gagal menghapus $file"
    else
      echo -e "   ${GREEN}✓${NC} Berhasil menghapus $file"
    fi
  done
fi

# 4. Perbaiki file EditService.php
echo -e "\n${YELLOW}4. Memperbaiki file EditService.php...${NC}"
cat > app/Filament/Resources/ServiceResource/Pages/EditService.php << 'EOL'
<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\Mechanic;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    public function mount($record): void
    {
        parent::mount($record);
        
        // Log untuk debugging
        Log::info("EditService: Mounting edit page for service #{$record->id}");
        
        // Pastikan mechanic_costs diisi dengan benar
        $this->fillMechanicCosts();
    }
    
    protected function fillMechanicCosts(): void
    {
        // Ambil data service
        $service = $this->record;
        
        // Jika tidak ada service, keluar
        if (!$service) {
            return;
        }
        
        // Log untuk debugging
        Log::info("EditService: Filling mechanic costs for service #{$service->id}");
        
        // Ambil data form saat ini
        $data = $this->data;
        
        // Jika mechanic_costs sudah diisi, keluar
        if (isset($data['mechanic_costs']) && is_array($data['mechanic_costs']) && !empty($data['mechanic_costs'])) {
            Log::info("EditService: Mechanic costs already filled", $data['mechanic_costs']);
            return;
        }
        
        // Siapkan mechanic_costs berdasarkan montir yang ada di database
        if ($service->mechanics()->count() > 0) {
            $mechanicCosts = [];
            
            foreach ($service->mechanics as $mechanic) {
                $laborCost = $mechanic->pivot->labor_cost;
                
                // Pastikan labor_cost tidak 0
                if (empty($laborCost) || $laborCost == 0) {
                    $laborCost = 50000; // Default labor cost
                }
                
                $mechanicCosts[] = [
                    'mechanic_id' => $mechanic->id,
                    'labor_cost' => $laborCost,
                ];
            }
            
            // Log mechanic_costs yang akan diisi ke form
            Log::info("EditService: Setting mechanic costs in mount", $mechanicCosts);
            
            // Tambahkan mechanic_costs ke data
            $data['mechanic_costs'] = $mechanicCosts;
            
            // Pastikan mechanics juga diisi dengan benar
            if (!isset($data['mechanics']) || !is_array($data['mechanics']) || empty($data['mechanics'])) {
                $data['mechanics'] = $service->mechanics()->pluck('mechanic_id')->toArray();
                Log::info("EditService: Setting mechanics in mount", $data['mechanics']);
            }
            
            // Update data form
            $this->form->fill($data);
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Log data yang akan diisi ke form
        Log::info("EditService: Form data before fill", $data);
        
        // Ambil data service
        $service = $this->record;
        
        // Siapkan mechanic_costs berdasarkan montir yang ada di database
        if ($service && $service->mechanics()->count() > 0) {
            $mechanicCosts = [];
            
            foreach ($service->mechanics as $mechanic) {
                $laborCost = $mechanic->pivot->labor_cost;
                
                // Pastikan labor_cost tidak 0
                if (empty($laborCost) || $laborCost == 0) {
                    $laborCost = 50000; // Default labor cost
                }
                
                $mechanicCosts[] = [
                    'mechanic_id' => $mechanic->id,
                    'labor_cost' => $laborCost,
                ];
            }
            
            // Log mechanic_costs yang akan diisi ke form
            Log::info("EditService: Mechanic costs data from database", $mechanicCosts);
            
            // Tambahkan mechanic_costs ke data
            $data['mechanic_costs'] = $mechanicCosts;
            
            // Pastikan mechanics juga diisi dengan benar
            if (!isset($data['mechanics']) || !is_array($data['mechanics']) || empty($data['mechanics'])) {
                $data['mechanics'] = $service->mechanics()->pluck('mechanic_id')->toArray();
                Log::info("EditService: Setting mechanics from database", $data['mechanics']);
            }
        }
        
        return $data;
    }
    
    protected function afterSave(): void
    {
        // Ambil data service yang baru disimpan
        $service = $this->record;
        
        // Log untuk debugging
        Log::info("EditService: After save for service #{$service->id}", [
            'status' => $service->status,
            'mechanics' => $service->mechanics()->pluck('mechanic_id')->toArray(),
        ]);
        
        // Jika status adalah completed, pastikan biaya jasa montir dipertahankan
        if ($service->status === 'completed') {
            // Ambil data form
            $formData = $this->data;
            
            // Log untuk debugging
            Log::info("EditService: Form data after save", $formData);
            
            // Periksa apakah ada mechanic_costs di form data
            if (isset($formData['mechanic_costs']) && is_array($formData['mechanic_costs'])) {
                // Dapatkan tanggal awal dan akhir minggu saat ini (Senin-Minggu)
                $now = now();
                $weekStart = $now->copy()->startOfWeek()->format('Y-m-d');
                $weekEnd = $now->copy()->endOfWeek()->format('Y-m-d');
                
                // Update pivot table dengan biaya jasa yang benar
                foreach ($formData['mechanic_costs'] as $costData) {
                    if (isset($costData['mechanic_id']) && isset($costData['labor_cost'])) {
                        $mechanicId = $costData['mechanic_id'];
                        $laborCost = (int)$costData['labor_cost'];
                        
                        // Pastikan biaya jasa tidak 0
                        if ($laborCost == 0) {
                            $laborCost = 50000; // Default labor cost
                        }
                        
                        Log::info("EditService: Updating labor cost for mechanic #{$mechanicId} to {$laborCost}");
                        
                        // Update pivot table
                        $service->mechanics()->updateExistingPivot($mechanicId, [
                            'labor_cost' => $laborCost,
                            'week_start' => $weekStart,
                            'week_end' => $weekEnd,
                        ]);
                    }
                }
                
                // Jalankan command untuk memperbarui rekap montir
                try {
                    Artisan::call('mechanic:sync-reports', [
                        '--service_id' => $service->id,
                    ]);
                    
                    Log::info("EditService: Mechanic reports synced for service #{$service->id}");
                } catch (\Exception $e) {
                    Log::error("EditService: Error syncing mechanic reports for service #{$service->id}: " . $e->getMessage());
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendFollowUpWhatsApp')
                ->label('Kirim Follow-up WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn() => $this->record->status === 'completed')
                ->form([
                    Forms\Components\Select::make('template')
                        ->label('Template Pesan')
                        ->options([
                            'follow_up' => 'Follow-up Standar',
                            'feedback' => 'Minta Feedback',
                            'promo' => 'Tawarkan Promo Berikutnya',
                        ])
                        ->default('follow_up')
                        ->required(),
                    Forms\Components\Textarea::make('custom_message')
                        ->label('Pesan Tambahan (Opsional)')
                        ->placeholder('Tambahkan pesan khusus di sini (opsional)')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    // Format nomor telepon untuk WhatsApp
                    $phone = preg_replace('/[^0-9]/', '', $this->record->phone);
                    if (substr($phone, 0, 1) === '0') {
                        $phone = '62' . substr($phone, 1);
                    } elseif (substr($phone, 0, 2) !== '62') {
                        $phone = '62' . $phone;
                    }

                    // Buat pesan berdasarkan template yang dipilih
                    $message = match ($data['template']) {
                        'follow_up' => "Halo {$this->record->customer_name},\n\n" .
                            "Terima kasih telah mempercayakan kendaraan Anda kepada Hartono Motor. " .
                            "Servis {$this->record->service_type} untuk mobil {$this->record->car_model} Anda telah selesai.\n\n" .
                            "Bagaimana kondisi kendaraan Anda setelah servis? Apakah ada masalah atau pertanyaan yang ingin Anda sampaikan?\n\n" .
                            "Kami sangat menghargai umpan balik Anda untuk meningkatkan layanan kami.\n\n" .
                            "Terima kasih,\nTim Hartono Motor",

                        'feedback' => "Halo {$this->record->customer_name},\n\n" .
                            "Terima kasih telah mempercayakan servis {$this->record->service_type} untuk mobil {$this->record->car_model} Anda kepada Hartono Motor.\n\n" .
                            "Kami ingin mengetahui pendapat Anda tentang layanan kami. Mohon berikan penilaian Anda dengan membalas pesan ini dengan angka 1-5 (1: Sangat Tidak Puas, 5: Sangat Puas).\n\n" .
                            "Kami juga sangat menghargai saran dan masukan Anda untuk meningkatkan kualitas layanan kami.\n\n" .
                            "Terima kasih,\nTim Hartono Motor",

                        'promo' => "Halo {$this->record->customer_name},\n\n" .
                            "Terima kasih telah mempercayakan servis {$this->record->service_type} untuk mobil {$this->record->car_model} Anda kepada Hartono Motor.\n\n" .
                            "Sebagai pelanggan setia kami, Anda berhak mendapatkan DISKON 10% untuk servis berikutnya dalam 3 bulan ke depan.\n\n" .
                            "Gunakan kode promo: HARTONO10\n\n" .
                            "Jangan lewatkan kesempatan ini untuk merawat kendaraan Anda dengan harga spesial!\n\n" .
                            "Terima kasih,\nTim Hartono Motor",

                        default => "Halo {$this->record->customer_name},\n\n" .
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
            Actions\DeleteAction::make(),
        ];
    }
}
EOL
echo -e "   ${GREEN}✓${NC} File EditService.php berhasil diperbaiki"

# 5. Jalankan kembali semua container
echo -e "\n${YELLOW}5. Menjalankan kembali semua container...${NC}"
docker-compose up -d
if [ $? -ne 0 ]; then
  echo -e "   ${RED}✗${NC} Gagal menjalankan kembali semua container"
  exit 1
else
  echo -e "   ${GREEN}✓${NC} Semua container berhasil dijalankan kembali"
fi

# 6. Tunggu beberapa detik
echo -e "\n${YELLOW}6. Menunggu beberapa detik...${NC}"
sleep 15
echo -e "   ${GREEN}✓${NC} Selesai menunggu"

# 7. Clear cache dan optimize
echo -e "\n${YELLOW}7. Membersihkan cache dan mengoptimalkan aplikasi...${NC}"
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan view:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan optimize
echo -e "   ${GREEN}✓${NC} Cache dibersihkan dan aplikasi dioptimalkan"

# 8. Periksa status container
echo -e "\n${YELLOW}8. Memeriksa status container...${NC}"
docker-compose ps
echo -e "   ${GREEN}✓${NC} Status container berhasil diperiksa"

echo -e "\n${GREEN}Perbaikan masalah duplicate method (versi 2) selesai!${NC}"
echo -e "${YELLOW}Catatan:${NC}"
echo -e "1. Semua file cadangan telah dihapus"
echo -e "2. File EditService.php telah diperbaiki dengan versi yang benar"
echo -e "3. Semua container telah di-restart"
echo -e "4. Cache telah dibersihkan dan aplikasi telah dioptimalkan"
echo -e "5. Jika masih ada masalah, coba periksa log aplikasi:"
echo -e "   ${YELLOW}docker-compose logs app${NC}"
