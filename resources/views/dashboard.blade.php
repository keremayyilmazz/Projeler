<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('GreenOpti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Harita Bölümü -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Fabrika Konumları</h3>
                    <div id="map" class="w-full" style="height: 500px; z-index: 1;"></div>
                </div>
            </div>

            <!-- Rota Hesaplama Bölümü -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Rota Hesaplama</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Başlangıç Fabrikası
                            </label>
                            <select id="source_factory_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Fabrika Seçin</option>
                                @foreach($factories as $factory)
                                    <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Hedef Fabrika
                            </label>
                            <select id="destination_factory_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Fabrika Seçin</option>
                                @foreach($factories as $factory)
                                    <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Taşıma Tipi
                            </label>
                            <select id="vehicle_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="land">Kara Taşımacılığı</option>
                                <option value="sea">Deniz Taşımacılığı</option>
                                <option value="air">Hava Taşımacılığı</option>
                                <option value="rail">Tren Taşımacılığı</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" id="calculateRoute" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Hesapla
                        </button>
                    </div>

                    <!-- Sonuçlar -->
                    <div id="results" class="mt-4 hidden">
                        <h4 class="font-semibold mb-2">Sonuçlar:</h4>
                        <div id="resultsContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <script>
        // Global değişkenler
        let map;
        let routeLayer = null;
        let markers = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Harita başlatma
            map = L.map('map').setView([39.9334, 32.8597], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Mevcut fabrikaları ekle
            @foreach($factories as $factory)
                addFactoryMarker(
                    {{ $factory->latitude }}, 
                    {{ $factory->longitude }}, 
                    "{{ $factory->name }}", 
                    {{ $factory->id }}
                );
            @endforeach

            // Haritaya tıklama olayı
            map.on('click', function(e) {
                var lat = e.latlng.lat.toFixed(6);
                var lng = e.latlng.lng.toFixed(6);

                var content = `
                    <form id="addFactoryForm" class="p-3">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fabrika Adı:</label>
                            <input type="text" name="name" class="w-full px-2 py-1 border rounded" required>
                        </div>
                        <input type="hidden" name="latitude" value="${lat}">
                        <input type="hidden" name="longitude" value="${lng}">
                        <button type="submit" class="w-full px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Kaydet
                        </button>
                    </form>
                `;

                var popup = L.popup()
                    .setLatLng(e.latlng)
                    .setContent(content)
                    .openOn(map);

                setTimeout(() => {
                    document.getElementById('addFactoryForm').addEventListener('submit', handleFactorySubmit);
                }, 100);
            });

            // Rota hesaplama butonu olayı
            document.getElementById('calculateRoute').addEventListener('click', calculateRoute);
        });

        function addFactoryMarker(lat, lng, name, id) {
            const marker = L.marker([lat, lng])
                .bindPopup(`
                    <div class="p-3">
                        <h3 class="font-semibold mb-2">${name}</h3>
                        <button onclick="deleteFactory(${id}, '${name}')" 
                            class="w-full px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                            Fabrikayı Sil
                        </button>
                    </div>
                `)
                .addTo(map);
            
            markers.push(marker);
        }

        async function handleFactorySubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);

            try {
                const response = await fetch('{{ route("factories.store") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    addFactoryMarker(
                        formData.get('latitude'),
                        formData.get('longitude'),
                        formData.get('name'),
                        data.id
                    );
                    
                    map.closePopup();
                    window.location.reload();
                } else {
                    alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu'));
                }
            } catch (error) {
                console.error('Hata:', error);
                alert('Bir hata oluştu! Detaylar için konsolu kontrol edin.');
            }
        }

        async function deleteFactory(id, name) {
            if (confirm(`"${name}" fabrikasını silmek istediğinizden emin misiniz?`)) {
                try {
                    const response = await fetch(`/factories/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        window.location.reload();
                    } else {
                        const data = await response.json();
                        alert('Hata: ' + (data.message || 'Fabrika silinirken bir hata oluştu'));
                    }
                } catch (error) {
                    console.error('Silme hatası:', error);
                    alert('Fabrika silinirken bir hata oluştu!');
                }
            }
        }

        async function calculateRoute() {
            const sourceId = document.getElementById('source_factory_id').value;
            const destinationId = document.getElementById('destination_factory_id').value;
            const vehicleType = document.getElementById('vehicle_type').value;

            if (!sourceId || !destinationId) {
                alert('Lütfen başlangıç ve hedef fabrikalarını seçin');
                return;
            }

            if (sourceId === destinationId) {
                alert('Başlangıç ve hedef fabrikaları aynı olamaz');
                return;
            }

            // Önceki rotayı temizle
            clearRoute();

            try {
                const response = await fetch('{{ route("calculations.calculate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        source_factory_id: sourceId,
                        destination_factory_id: destinationId,
                        vehicle_type: vehicleType
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Rotayı haritada göster
                    if (data.geometry) {
                        showRoute(data.geometry, vehicleType);
                    }
                    
                    // Sonuçları göster
                    showResults(data);
                } else {
                    showError(data);
                }
            } catch (error) {
                console.error('Hata:', error);
                showError({ message: 'Rota hesaplanırken bir hata oluştu' });
            }
        }

        function showRoute(geometry, vehicleType) {
            if (routeLayer) {
                map.removeLayer(routeLayer);
            }

            const routeStyle = getRouteStyle(vehicleType);

            routeLayer = L.geoJSON(geometry, {
                style: routeStyle
            }).addTo(map);

            map.fitBounds(routeLayer.getBounds());
        }

        function getRouteStyle(vehicleType) {
            const styles = {
                'land': { color: '#FF4444', weight: 3, opacity: 0.7 },
                'rail': { color: '#4444FF', weight: 3, opacity: 0.7 },
                'sea': { color: '#44AAFF', weight: 3, opacity: 0.7 },
                'air': { color: '#00B300', weight: 3, opacity: 0.7, dashArray: '5, 10' }
            };

            return styles[vehicleType] || styles.land;
        }

        function clearRoute() {
            if (routeLayer) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }
        }

        function showResults(data) {
            const vehicleTypes = {
                'land': 'Kara Taşımacılığı',
                'sea': 'Deniz Taşımacılığı',
                'air': 'Hava Taşımacılığı',
                'rail': 'Tren Taşımacılığı'
            };

            const resultsDiv = document.getElementById('results');
            const resultsContent = document.getElementById('resultsContent');

            resultsContent.innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded p-4">
                    <p class="mb-2"><strong>${data.source_factory}</strong> ile <strong>${data.destination_factory}</strong> arası:</p>
                    <p class="mb-1">Mesafe: ${data.distance} km</p>
                    <p>Tahmini Süre: ${data.duration} saat (${vehicleTypes[data.vehicle_type]} ile)</p>
                </div>
            `;
            resultsDiv.classList.remove('hidden');
        }

        function showError(data) {
            const resultsDiv = document.getElementById('results');
            const resultsContent = document.getElementById('resultsContent');

            let errorClass = 'bg-red-50 border-red-200';
            let errorIcon = '';
            
            if (data.error_type === 'sea_transport_not_possible') {
                errorClass = 'bg-yellow-50 border-yellow-200';
                errorIcon = '<svg class="w-5 h-5 text-yellow-400 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
            }

            resultsContent.innerHTML = `
                <div class="${errorClass} border rounded p-4 text-gray-700">
                    ${errorIcon}${data.message}
                    ${data.errors ? '<ul class="mt-2 list-disc list-inside">' + 
                        Object.values(data.errors).map(err => `<li>${err}</li>`).join('') + 
                        '</ul>' : ''}
                </div>
            `;
            resultsDiv.classList.remove('hidden');
        }
    </script>
    @endpush
</x-app-layout>