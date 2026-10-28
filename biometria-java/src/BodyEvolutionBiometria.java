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
    private static volatile boolean escuchando;
    private static volatile long ignoreUntil;
    private static volatile String enrollmentClient = "";
    private static volatile int enrollmentSamples;
    private static volatile int enrollmentSamplesNeeded = 4;
    private static volatile String enrollmentState = "inactivo";
    private static volatile String enrollmentMessage = "Listo para registrar la huella.";
    private static volatile String enrollmentError = "";
    private static volatile String enrollmentTemplate = "";
    private static long sampleSeq;
    private static String lastSample;
    private static final Pattern FIELD = Pattern.compile("\"([^\"]+)\"\\s*:\\s*\"([^\"]*)\"");

    private BodyEvolutionBiometria() {
    }

    public static void main(String[] args) throws IOException {
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", PORT), 0);
        server.createContext("/salud", exchange -> respond(exchange, 200, salud()));
        server.createContext("/capturar", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::capturar, body)));
        server.createContext("/enrolar/iniciar", exchange -> handle(exchange, BodyEvolutionBiometria::iniciarEnrolar));
        server.createContext("/enrolar/progreso", exchange -> handle(exchange, BodyEvolutionBiometria::progresoEnrolar));
        server.createContext("/enrolar/resultado", exchange -> handle(exchange, BodyEvolutionBiometria::resultadoEnrolar));
        server.createContext("/enrolar", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::enrolar, body)));
        server.createContext("/verificar", exchange -> handle(exchange, BodyEvolutionBiometria::verificar));
        server.createContext("/poll", exchange -> respond(exchange, 200, poll(exchange.getRequestURI().getRawQuery())));
        server.setExecutor(java.util.concurrent.Executors.newFixedThreadPool(4));
        server.start();
        startListener();
        System.out.println("Puente del lector activo en http://127.0.0.1:" + PORT);
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
                            if (!escuchando || System.currentTimeMillis() < ignoreUntil) {
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
                        if (escuchando) {
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

    private static void pausarEscuchador() {
        synchronized (LISTENER_LOCK) {
            escuchando = false;
            if (listener != null) {
                listener.stopCapture();
            }
        }
    }

    private static void continuarEscuchador() {
        synchronized (LISTENER_LOCK) {
            ignoreUntil = System.currentTimeMillis() + 1500;
            escuchando = true;
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
            pausarEscuchador();
            return handler.handle(body);
        } finally {
            continuarEscuchador();
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

    private static String salud() {
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

    private static String capturar(String body) throws Exception {
        String terminal = field(body, "terminal");
        return "{\"sample\":\"" + captureSample(terminal) + "\"}";
    }

    private static String enrolar(String body) throws Exception {
        String cliente = field(body, "cliente");
        String terminal = field(body, "terminal");
        DPFPEnrollment enrollment = DPFPGlobal.getEnrollmentFactory().createEnrollment();
        DPFPFeatureExtraction extraction = DPFPGlobal.getFeatureExtractionFactory().createFeatureExtraction();
        int totalSamples = enrollment.getFeaturesNeeded();

        enrollmentClient = cliente;
        enrollmentSamples = 0;
        enrollmentSamplesNeeded = totalSamples;
        enrollmentMessage = "Coloca el dedo en el lector.";

        while (enrollment.getFeaturesNeeded() > 0) {
            DPFPSample sample = captureSampleObject(terminal);
            try {
                DPFPFeatureSet features = extraction.createFeatureSet(
                    sample,
                    DPFPDataPurpose.DATA_PURPOSE_ENROLLMENT
                );
                enrollment.addFeatures(features);
                enrollmentSamples = totalSamples - enrollment.getFeaturesNeeded();
                enrollmentMessage = enrollmentSamples < totalSamples
                    ? "Captura " + enrollmentSamples + " de " + totalSamples + " aceptada. Retira el dedo y colócalo de nuevo."
                    : "Las cuatro capturas fueron aceptadas.";
            } catch (DPFPImageQualityException ignored) {
                enrollmentMessage = "La lectura no fue clara. Limpia el dedo y vuelve a colocarlo.";
            }
        }

        DPFPTemplate template = enrollment.getTemplate();
        if (template == null) {
            throw new IllegalStateException("DigitalPersona no pudo crear la plantilla.");
        }

        return "{\"template\":\"" + encode(template.serialize()) + "\"}";
    }

    private static String iniciarEnrolar(String body) {
        String cliente = field(body, "cliente");
        String terminal = field(body, "terminal");

        synchronized (STATE_LOCK) {
            if ("en_curso".equals(enrollmentState)) {
                throw new IllegalStateException("Ya hay un enrolamiento en curso.");
            }

            enrollmentClient = cliente;
            enrollmentSamples = 0;
            enrollmentSamplesNeeded = 4;
            enrollmentMessage = "Coloca el dedo en el lector.";
            enrollmentError = "";
            enrollmentTemplate = "";
            enrollmentState = "en_curso";
        }

        CompletableFuture.runAsync(() -> {
            try {
                String result = exclusive(BodyEvolutionBiometria::enrolar, body);
                synchronized (STATE_LOCK) {
                    enrollmentTemplate = field(result, "template");
                    enrollmentState = "completado";
                    enrollmentMessage = "Las cuatro capturas fueron aceptadas.";
                }
            } catch (Exception | LinkageError exception) {
                String message = exception.getMessage() == null ? "Error biométrico." : exception.getMessage();
                synchronized (STATE_LOCK) {
                    enrollmentError = message;
                    enrollmentMessage = message;
                    enrollmentState = "error";
                }
            }
        });

        return "{\"en_curso\":true}";
    }

    private static String progresoEnrolar(String body) {
        String cliente = field(body, "cliente");
        boolean esEnrolamientoActual = cliente.equals(enrollmentClient);
        String estado = esEnrolamientoActual ? enrollmentState : "inactivo";
        int muestras = esEnrolamientoActual ? enrollmentSamples : 0;
        int total = esEnrolamientoActual ? Math.max(1, enrollmentSamplesNeeded) : 4;
        String mensaje = esEnrolamientoActual ? enrollmentMessage : "Listo para registrar la huella.";
        String error = esEnrolamientoActual ? enrollmentError : "";
        boolean enCurso = "en_curso".equals(estado);

        return "{\"muestras\":" + muestras
            + ",\"total\":" + total
            + ",\"en_curso\":" + enCurso
            + ",\"estado\":\"" + escape(estado)
            + "\",\"mensaje\":\"" + escape(mensaje)
            + "\",\"error\":\"" + escape(error) + "\"}";
    }

    private static String resultadoEnrolar(String body) {
        String cliente = field(body, "cliente");

        synchronized (STATE_LOCK) {
            if (!cliente.equals(enrollmentClient) || !"completado".equals(enrollmentState) || enrollmentTemplate.isEmpty()) {
                throw new IllegalStateException("El enrolamiento todavía no está completo.");
            }

            String template = enrollmentTemplate;
            enrollmentClient = "";
            enrollmentTemplate = "";
            enrollmentError = "";
            enrollmentState = "inactivo";
            enrollmentMessage = "Listo para registrar la huella.";

            return "{\"template\":\"" + template + "\"}";
        }
    }

    private static String verificar(String body) throws Exception {
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
