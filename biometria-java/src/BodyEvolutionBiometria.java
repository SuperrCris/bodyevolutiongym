import com.digitalpersona.onetouch.DPFPDataPurpose;
import com.digitalpersona.onetouch.DPFPFeatureSet;
import com.digitalpersona.onetouch.DPFPGlobal;
import com.digitalpersona.onetouch.DPFPSample;
import com.digitalpersona.onetouch.DPFPTemplate;
import com.digitalpersona.onetouch.capture.DPFPCapture;
import com.digitalpersona.onetouch.capture.DPFPCapturePriority;
import com.digitalpersona.onetouch.capture.event.DPFPDataAdapter;
import com.digitalpersona.onetouch.capture.event.DPFPDataEvent;
import com.digitalpersona.onetouch.capture.event.DPFPErrorEvent;
import com.digitalpersona.onetouch.capture.event.DPFPErrorListener;
import com.digitalpersona.onetouch.capture.event.DPFPReaderStatusAdapter;
import com.digitalpersona.onetouch.capture.event.DPFPReaderStatusEvent;
import com.digitalpersona.onetouch.capture.event.DPFPSensorAdapter;
import com.digitalpersona.onetouch.capture.event.DPFPSensorEvent;
import com.digitalpersona.onetouch.readers.DPFPReaderDescription;
import com.digitalpersona.onetouch.processing.DPFPEnrollment;
import com.digitalpersona.onetouch.processing.DPFPFeatureExtraction;
import com.digitalpersona.onetouch.processing.DPFPImageQualityException;
import com.digitalpersona.onetouch.verification.DPFPVerificationResult;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.Base64;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;
import java.util.concurrent.locks.ReentrantLock;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class BodyEvolutionBiometria {
    private static final int PORT = 8765;
    private static final long CAPTURE_TIMEOUT_SECONDS = 40;
    private static final ReentrantLock CAPTURE_LOCK = new ReentrantLock();
    private static final Object LISTENER_LOCK = new Object();
    private static final Object STATE_LOCK = new Object();
    private static volatile DPFPCapture listener;
    private static volatile boolean listenerPaused;
    private static volatile long ignoreUntil;
    private static long sampleSeq;
    private static String lastSample;
    private static final Pattern FIELD = Pattern.compile("\"([^\"]+)\"\\s*:\\s*\"([^\"]*)\"");

    private BodyEvolutionBiometria() {
    }

    public static void main(String[] args) throws IOException {
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", PORT), 0);
        server.createContext("/health", exchange -> respond(exchange, 200, health()));
        server.createContext("/capture", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::capture, body)));
        server.createContext("/enroll", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::enroll, body)));
        server.createContext("/verify", exchange -> handle(exchange, BodyEvolutionBiometria::verify));
        server.createContext("/poll", exchange -> respond(exchange, 200, poll(exchange.getRequestURI().getRawQuery())));
        server.setExecutor(java.util.concurrent.Executors.newFixedThreadPool(4));
        server.start();
        startListener();
        System.out.println("Body Evolution biometric bridge listening on http://127.0.0.1:" + PORT);
    }

    private static void startListener() {
        Thread thread = new Thread(() -> {
            while (listener == null) {
                try {
                    if (DPFPGlobal.getReadersFactory().getReaders().isEmpty()) {
                        throw new IllegalStateException("Sin lector conectado.");
                    }
                    DPFPCapture capture = DPFPGlobal.getCaptureFactory().createCapture();
                    capture.setPriority(DPFPCapturePriority.CAPTURE_PRIORITY_LOW);
                    capture.addDataListener(new DPFPDataAdapter() {
                        @Override
                        public void dataAcquired(DPFPDataEvent event) {
                            if (listenerPaused || System.currentTimeMillis() < ignoreUntil) {
                                return;
                            }
                            String encoded = encode(event.getSample().serialize());
                            synchronized (STATE_LOCK) {
                                lastSample = encoded;
                                sampleSeq++;
                            }
                        }
                    });
                    synchronized (LISTENER_LOCK) {
                        listener = capture;
                        if (!listenerPaused) {
                            capture.startCapture();
                        }
                    }
                    System.out.println("Lectura continua activa.");
                } catch (Exception | LinkageError exception) {
                    System.out.println("Lectura continua en espera: " + exception.getMessage());
                    try {
                        Thread.sleep(5000);
                    } catch (InterruptedException interrupted) {
                        return;
                    }
                }
            }
        }, "lectura-continua");
        thread.setDaemon(true);
        thread.start();
    }

    private static void pauseListener() {
        synchronized (LISTENER_LOCK) {
            listenerPaused = true;
            if (listener != null) {
                listener.stopCapture();
            }
        }
    }

    private static void resumeListener() {
        synchronized (LISTENER_LOCK) {
            ignoreUntil = System.currentTimeMillis() + 1500;
            listenerPaused = false;
            if (listener != null) {
                listener.startCapture();
            }
        }
    }

    private static String exclusive(Handler handler, String body) throws Exception {
        if (!CAPTURE_LOCK.tryLock()) {
            throw new IllegalStateException("El lector está ocupado con otra captura.");
        }
        try {
            pauseListener();
            return handler.handle(body);
        } finally {
            resumeListener();
            CAPTURE_LOCK.unlock();
        }
    }

    private static String poll(String query) {
        long after = -1;
        if (query != null) {
            Matcher matcher = Pattern.compile("(?:^|&)after=(\\d+)").matcher(query);
            if (matcher.find()) {
                after = Long.parseLong(matcher.group(1));
            }
        }
        synchronized (STATE_LOCK) {
            if (after >= 0 && sampleSeq > after && lastSample != null) {
                return "{\"seq\":" + sampleSeq + ",\"sample\":\"" + lastSample + "\"}";
            }
            return "{\"seq\":" + sampleSeq + "}";
        }
    }

    private static String health() {
        try {
            StringBuilder readers = new StringBuilder();
            for (DPFPReaderDescription reader : DPFPGlobal.getReadersFactory().getReaders()) {
                if (readers.length() > 0) {
                    readers.append(',');
                }
                readers.append("{\"serial\":\"").append(escape(String.valueOf(reader.getSerialNumber())))
                    .append("\",\"product\":\"").append(escape(String.valueOf(reader.getProductName())))
                    .append("\"}");
            }
            return "{\"status\":\"ok\",\"readers\":[" + readers + "]}";
        } catch (Exception | LinkageError exception) {
            return "{\"status\":\"error\",\"error\":\"" + escape(String.valueOf(exception)) + "\"}";
        }
    }

    private static String capture(String body) throws Exception {
        String terminal = field(body, "terminal");
        return "{\"sample\":\"" + captureSample(terminal) + "\"}";
    }

    private static String enroll(String body) throws Exception {
        String terminal = field(body, "terminal");
        DPFPEnrollment enrollment = DPFPGlobal.getEnrollmentFactory().createEnrollment();
        DPFPFeatureExtraction extraction = DPFPGlobal.getFeatureExtractionFactory().createFeatureExtraction();

        while (enrollment.getFeaturesNeeded() > 0) {
            DPFPSample sample = captureSampleObject(terminal);
            try {
                DPFPFeatureSet features = extraction.createFeatureSet(
                    sample,
                    DPFPDataPurpose.DATA_PURPOSE_ENROLLMENT
                );
                enrollment.addFeatures(features);
            } catch (DPFPImageQualityException ignored) {
                // The reader asks for another impression when image quality is insufficient.
            }
        }

        DPFPTemplate template = enrollment.getTemplate();
        if (template == null) {
            throw new IllegalStateException("DigitalPersona no pudo crear la plantilla.");
        }

        return "{\"template\":\"" + encode(template.serialize()) + "\"}";
    }

    private static String verify(String body) throws Exception {
        byte[] sampleBytes = decode(field(body, "sample"));
        byte[] templateBytes = decode(field(body, "template"));
        DPFPSample sample = DPFPGlobal.getSampleFactory().createSample(sampleBytes);
        DPFPFeatureSet features = DPFPGlobal.getFeatureExtractionFactory()
            .createFeatureExtraction()
            .createFeatureSet(sample, DPFPDataPurpose.DATA_PURPOSE_VERIFICATION);
        DPFPTemplate template = DPFPGlobal.getTemplateFactory().createTemplate(templateBytes);
        DPFPVerificationResult result = DPFPGlobal.getVerificationFactory()
            .createVerification()
            .verify(features, template);

        return "{\"matched\":" + result.isVerified() + "}";
    }

    private static DPFPSample captureSampleObject(String terminal) throws Exception {
        if (!CAPTURE_LOCK.tryLock()) {
            throw new IllegalStateException("El lector está ocupado con otra captura.");
        }

        try {
            if (DPFPGlobal.getReadersFactory().getReaders().isEmpty()) {
                throw new IllegalStateException("No se detecta ningún lector de huellas conectado.");
            }

            DPFPCapture capture = DPFPGlobal.getCaptureFactory().createCapture();
            capture.setPriority(DPFPCapturePriority.CAPTURE_PRIORITY_LOW);
            if (terminal != null && !terminal.isEmpty()) {
                capture.setReaderSerialNumber(terminal);
            }

            CompletableFuture<DPFPSample> result = new CompletableFuture<>();
            boolean[] touched = {false};
            capture.addDataListener(new DPFPDataAdapter() {
                @Override
                public void dataAcquired(DPFPDataEvent event) {
                    result.complete(event.getSample());
                }
            });
            capture.addSensorListener(new DPFPSensorAdapter() {
                @Override
                public void fingerTouched(DPFPSensorEvent event) {
                    touched[0] = true;
                }
            });
            capture.addReaderStatusListener(new DPFPReaderStatusAdapter() {
                @Override
                public void readerDisconnected(DPFPReaderStatusEvent event) {
                    result.completeExceptionally(new IllegalStateException("El lector se desconectó."));
                }
            });
            capture.addErrorListener(new DPFPErrorListener() {
                @Override
                public void errorOccured(DPFPErrorEvent event) {
                    result.completeExceptionally(new IllegalStateException("Error del lector: " + event.getError()));
                }

                @Override
                public void exceptionCaught(DPFPErrorEvent event) {
                    result.completeExceptionally(new IllegalStateException("Error del lector: " + event.getError()));
                }
            });
            capture.startCapture();
            System.out.println("Captura iniciada (prioridad baja, iniciada=" + capture.isStarted() + ")");

            try {
                return result.get(CAPTURE_TIMEOUT_SECONDS, TimeUnit.SECONDS);
            } catch (ExecutionException exception) {
                throw exception.getCause() instanceof Exception cause ? cause : exception;
            } catch (TimeoutException exception) {
                throw new IllegalStateException(touched[0]
                    ? "Se detectó el dedo pero no se obtuvo una imagen válida. Vuelva a intentarlo."
                    : "No se recibió una huella dentro del tiempo permitido.", exception);
            } finally {
                capture.stopCapture();
            }
        } finally {
            CAPTURE_LOCK.unlock();
        }
    }

    private static String captureSample(String terminal) throws Exception {
        return encode(captureSampleObject(terminal).serialize());
    }

    private static String field(String body, String name) {
        Matcher matcher = FIELD.matcher(body);
        while (matcher.find()) {
            if (matcher.group(1).equals(name)) {
                return matcher.group(2).replace("\\/", "/");
            }
        }
        throw new IllegalArgumentException("Falta el campo: " + name);
    }

    private static String encode(byte[] value) {
        return Base64.getEncoder().encodeToString(value);
    }

    private static byte[] decode(String value) {
        return Base64.getDecoder().decode(value);
    }

    private static void handle(HttpExchange exchange, Handler handler) throws IOException {
        try {
            String body = new String(exchange.getRequestBody().readAllBytes(), StandardCharsets.UTF_8);
            respond(exchange, 200, handler.handle(body));
        } catch (Exception | LinkageError exception) {
            String message = exception.getMessage() == null ? "Error biométrico." : exception.getMessage();
            respond(exchange, 422, "{\"error\":\"" + escape(message) + "\"}");
        }
    }

    private static void respond(HttpExchange exchange, int status, String body) throws IOException {
        byte[] content = body.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().set("Content-Type", "application/json; charset=utf-8");
        exchange.getResponseHeaders().set("Access-Control-Allow-Origin", "http://localhost");
        exchange.sendResponseHeaders(status, content.length);
        try (OutputStream output = exchange.getResponseBody()) {
            output.write(content);
        }
    }

    private static String escape(String value) {
        return value.replace("\\", "\\\\").replace("\"", "\\\"");
    }

    @FunctionalInterface
    private interface Handler {
        String handle(String body) throws Exception;
    }
}
