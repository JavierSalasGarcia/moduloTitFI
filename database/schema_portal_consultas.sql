-- Portada conversacional y proveedor opcional NVIDIA NIM.
-- Idempotente: puede ejecutarse más de una vez.
INSERT INTO ajustes (clave, valor, descripcion) VALUES
('ia_nvidia_api_key', '', 'API key de NVIDIA NIM (opcional; vacío = motor local)'),
('ia_nvidia_modelo', 'meta/llama-3.1-8b-instruct', 'Modelo de chat de NVIDIA NIM'),
('ia_nvidia_url', 'https://integrate.api.nvidia.com/v1/chat/completions', 'Endpoint compatible con OpenAI de NVIDIA NIM')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
