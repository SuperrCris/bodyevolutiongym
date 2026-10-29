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
import java.awt.Color;
import java.awt.Font;
import java.awt.FontMetrics;
import java.awt.Graphics;
import java.awt.Graphics2D;
import java.awt.RenderingHints;
import java.awt.font.FontRenderContext;
import java.awt.print.PageFormat;
import java.awt.print.Paper;
import java.awt.print.Printable;
import java.awt.print.PrinterJob;
import java.awt.image.BufferedImage;
import java.io.ByteArrayInputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Base64;
import java.util.List;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;
import java.util.concurrent.locks.ReentrantLock;
import javax.imageio.ImageIO;
import javax.print.DocFlavor;
import javax.print.PrintService;
import javax.print.PrintServiceLookup;
import javax.print.attribute.standard.Media;
import javax.print.attribute.standard.MediaSize;
import javax.print.attribute.standard.MediaSizeName;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class BodyEvolutionBiometria {
    private static final int PORT = 8765;
    private static final long CAPTURE_TIMEOUT_SECONDS = 40;
    private static final int MAX_PRINT_REQUEST_BYTES = 64000;
    private static final int DEFAULT_RECEIPT_COPIES = 2;
    private static final int MAX_RECEIPT_COPIES = 2;
    private static final String RECEIPT_HEADER_V1 = "BEG-PRINT-V1\n";
    private static final String RECEIPT_HEADER_V2 = "BEG-PRINT-V2\n";
    private static final String BOLD_MARKER = "?n";
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
    private static final Pattern CANDIDATE = Pattern.compile("\"id\"\\s*:\\s*\"([^\"]*)\"\\s*,\\s*\"template\"\\s*:\\s*\"([^\"]*)\"");
    private static final Pattern FIND_ALL = Pattern.compile("\"todas\"\\s*:\\s*true");
    private static final Pattern ROLL_WIDTH = Pattern.compile("^(\\d+(?:\\.\\d+)?)\\s*\\(");

    private record ReceiptLine(String text, boolean bold) {
    }

    private BodyEvolutionBiometria() {
    }

    public static void main(String[] args) throws IOException {
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", PORT), 0);
        server.createContext("/salud", exchange -> respond(exchange, 200, salud()));
        server.createContext("/impresora", exchange -> {
            String printer = impresoraPredeterminada();
            respond(exchange, printer == null ? 503 : 200, printer == null
                ? "{\"available\":false,\"error\":\"Windows no tiene una impresora predeterminada disponible.\"}"
                : "{\"available\":true,\"printer\":\"" + escape(printer) + "\"}");
        });
        server.createContext("/imprimir", BodyEvolutionBiometria::handleImpresion);
        server.createContext("/capturar", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::capturar, body)));
        server.createContext("/enrolar/iniciar", exchange -> handle(exchange, BodyEvolutionBiometria::iniciarEnrolar));
        server.createContext("/enrolar/progreso", exchange -> handle(exchange, BodyEvolutionBiometria::progresoEnrolar));
        server.createContext("/enrolar/resultado", exchange -> handle(exchange, BodyEvolutionBiometria::resultadoEnrolar));
        server.createContext("/enrolar", exchange -> handle(exchange, body -> exclusive(BodyEvolutionBiometria::enrolar, body)));
        server.createContext("/verificar", exchange -> handle(exchange, BodyEvolutionBiometria::verificar));
        server.createContext("/identificar", exchange -> handle(exchange, BodyEvolutionBiometria::identificar));
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

    private static String impresoraPredeterminada() {
        PrintService printer = PrintServiceLookup.lookupDefaultPrintService();
        return printer == null ? null : printer.getName();
    }

    private static String imprimir(String contenido) throws Exception {
        int logoStart;
        int copies;

        if (contenido.startsWith(RECEIPT_HEADER_V2)) {
            int copiesEnd = contenido.indexOf('\n', RECEIPT_HEADER_V2.length());
            if (copiesEnd < 0) {
                throw new IllegalArgumentException("El recibo no incluye la cantidad de tickets.");
            }

            try {
                copies = Integer.parseInt(contenido.substring(RECEIPT_HEADER_V2.length(), copiesEnd));
            } catch (NumberFormatException exception) {
                throw new IllegalArgumentException("La cantidad de tickets no tiene un formato válido.", exception);
            }

            if (copies < 1 || copies > MAX_RECEIPT_COPIES) {
                throw new IllegalArgumentException("La cantidad de tickets solicitada no es válida.");
            }
            logoStart = copiesEnd + 1;
        } else if (contenido.startsWith(RECEIPT_HEADER_V1)) {
            copies = DEFAULT_RECEIPT_COPIES;
            logoStart = RECEIPT_HEADER_V1.length();
        } else {
            throw new IllegalArgumentException("El contenido del recibo no tiene un formato válido.");
        }

        int separator = contenido.indexOf("\n\n", logoStart);
        if (separator < 0) {
            throw new IllegalArgumentException("El recibo no incluye el logo.");
        }

        byte[] logoBytes;
        try {
            logoBytes = Base64.getDecoder().decode(contenido.substring(logoStart, separator));
        } catch (IllegalArgumentException exception) {
            throw new IllegalArgumentException("El logo del recibo no tiene un formato válido.", exception);
        }
        BufferedImage sourceLogo = ImageIO.read(new ByteArrayInputStream(logoBytes));
        if (sourceLogo == null || sourceLogo.getWidth() <= 0 || sourceLogo.getHeight() <= 0
            || (long) sourceLogo.getWidth() * sourceLogo.getHeight() > 2_000_000) {
            throw new IllegalArgumentException("No se pudo leer el logo del recibo.");
        }

        String recibo = contenido.substring(separator + 2);
        if (recibo.isBlank()) {
            throw new IllegalArgumentException("El recibo está vacío.");
        }
        if (recibo.length() > 12000) {
            throw new IllegalArgumentException("El contenido del recibo excede el límite permitido.");
        }

        PrintService printer = PrintServiceLookup.lookupDefaultPrintService();
        if (printer == null) {
            throw new IllegalStateException("Windows no tiene una impresora predeterminada disponible.");
        }
        if (!printer.isDocFlavorSupported(DocFlavor.SERVICE_FORMATTED.PRINTABLE)) {
            throw new IllegalStateException("La impresora predeterminada no admite recibos gráficos.");
        }

        PrinterJob job = PrinterJob.getPrinterJob();
        job.setPrintService(printer);

        PageFormat page = job.defaultPage();
        Paper defaultPaper = page.getPaper();
        double paperWidth = widthFromDefaultMedia(printer, defaultPaper);
        double printableWidth = printableWidthFromDefaultMedia(printer, defaultPaper);
        double marginX = millimeters(4);
        double marginY = millimeters(4);
        printableWidth = Math.min(printableWidth, paperWidth - (marginX * 2));
        if (printableWidth <= 0) {
            throw new IllegalStateException("No se pudo determinar el ancho imprimible de la impresora.");
        }

        Font font = new Font(Font.MONOSPACED, Font.PLAIN, 9);
        Font boldFont = font.deriveFont(Font.BOLD);
        List<ReceiptLine> lines = wrapReceipt(recibo, font, boldFont, printableWidth);
        FontRenderContext fontRenderContext = new FontRenderContext(null, true, true);
        double lineHeight = Math.max(
            font.getLineMetrics("Ag", fontRenderContext).getHeight(),
            boldFont.getLineMetrics("Ag", fontRenderContext).getHeight()
        ) + 1;
        BufferedImage logo = thermalLogo(sourceLogo);
        double logoWidth = Math.min(printableWidth * 0.94, millimeters(60));
        double logoHeight = logoWidth * logo.getHeight() / logo.getWidth();
        double logoGap = millimeters(2);
        double pageHeight = Math.max(
            millimeters(50),
            marginY + logoHeight + logoGap + (lineHeight * lines.size()) + marginY
        );

        Paper paper = new Paper();
        paper.setSize(paperWidth, pageHeight);
        paper.setImageableArea(marginX, marginY, printableWidth, pageHeight - (marginY * 2));
        page.setOrientation(PageFormat.PORTRAIT);
        page.setPaper(paper);
        PageFormat printablePage = job.validatePage(page);

        if (printablePage.getImageableHeight() < logoHeight + logoGap + (lineHeight * lines.size())) {
            throw new IllegalStateException("El recibo excede el tamaño de papel configurado en la impresora.");
        }

        job.setPrintable((Graphics graphics, PageFormat format, int pageIndex) -> {
            if (pageIndex >= copies) {
                return Printable.NO_SUCH_PAGE;
            }

            Graphics2D printerGraphics = (Graphics2D) graphics.create();
            try {
                printerGraphics.setColor(Color.BLACK);
                printerGraphics.setFont(font);
                printerGraphics.setRenderingHint(
                    RenderingHints.KEY_INTERPOLATION,
                    RenderingHints.VALUE_INTERPOLATION_BILINEAR
                );
                FontMetrics metrics = printerGraphics.getFontMetrics();
                float x = (float) format.getImageableX();
                double logoX = format.getImageableX() + (format.getImageableWidth() - logoWidth) / 2;
                printerGraphics.drawImage(
                    logo,
                    (int) Math.round(logoX),
                    (int) Math.round(format.getImageableY()),
                    (int) Math.round(logoWidth),
                    (int) Math.round(logoHeight),
                    null
                );
                float y = (float) (format.getImageableY() + logoHeight + logoGap) + metrics.getAscent();

                for (ReceiptLine line : lines) {
                    printerGraphics.setFont(line.bold() ? boldFont : font);
                    printerGraphics.drawString(line.text(), x, y);
                    y += (float) lineHeight;
                }

                return Printable.PAGE_EXISTS;
            } finally {
                printerGraphics.dispose();
            }
        }, printablePage);

        job.print();
        return "{\"printed\":true,\"printer\":\"" + escape(printer.getName())
            + "\",\"copies\":" + copies + "}";
    }

    private static BufferedImage thermalLogo(BufferedImage source) {
        BufferedImage logo = new BufferedImage(source.getWidth(), source.getHeight(), BufferedImage.TYPE_INT_RGB);

        for (int y = 0; y < source.getHeight(); y++) {
            for (int x = 0; x < source.getWidth(); x++) {
                int alpha = (source.getRGB(x, y) >>> 24) & 0xFF;
                int color = 255 - alpha;
                logo.setRGB(x, y, (color << 16) | (color << 8) | color);
            }
        }

        return logo;
    }

    private static double widthFromDefaultMedia(PrintService printer, Paper defaultPaper) {
        Media media = (Media) printer.getDefaultAttributeValue(Media.class);
        if (media instanceof MediaSizeName mediaName) {
            Matcher matcher = ROLL_WIDTH.matcher(mediaName.toString());
            if (matcher.find()) {
                return millimeters(Double.parseDouble(matcher.group(1)));
            }
        }

        return defaultPaper.getWidth();
    }

    private static double printableWidthFromDefaultMedia(PrintService printer, Paper defaultPaper) {
        Media media = (Media) printer.getDefaultAttributeValue(Media.class);
        if (media instanceof MediaSizeName mediaName) {
            MediaSize mediaSize = MediaSize.getMediaSizeForName(mediaName);
            if (mediaSize != null) {
                return millimeters(mediaSize.getX(MediaSize.MM));
            }
        }

        return defaultPaper.getImageableWidth();
    }

    private static List<ReceiptLine> wrapReceipt(String content, Font font, Font boldFont, double width) {
        FontRenderContext context = new FontRenderContext(null, true, true);
        List<ReceiptLine> lines = new ArrayList<>();

        for (String sourceLine : content.split("\\R", -1)) {
            boolean bold = sourceLine.contains(BOLD_MARKER);
            String text = sourceLine.replace(BOLD_MARKER, "");
            Font lineFont = bold ? boldFont : font;

            if (text.isEmpty()) {
                lines.add(new ReceiptLine("", bold));
                continue;
            }

            StringBuilder line = new StringBuilder();
            for (int offset = 0; offset < text.length();) {
                int codePoint = text.codePointAt(offset);
                String character = new String(Character.toChars(codePoint));
                String candidate = line.toString() + character;

                if (!line.isEmpty() && lineFont.getStringBounds(candidate, context).getWidth() > width) {
                    lines.add(new ReceiptLine(line.toString(), bold));
                    line.setLength(0);
                }

                line.append(character);
                offset += Character.charCount(codePoint);
            }
            lines.add(new ReceiptLine(line.toString(), bold));
        }

        return lines;
    }

    private static double millimeters(double value) {
        return value * 72.0 / 25.4;
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

    /**
     * Identificación 1:N: extrae las características de la muestra una sola vez y la compara
     * con cada plantilla candidata del lote. Con "todas":true no se detiene en la primera coincidencia.
     */
    private static String identificar(String body) throws Exception {
        DPFPSample sample = DPFPGlobal.getSampleFactory().createSample(decode(field(body, "sample")));
        DPFPFeatureSet features = DPFPGlobal.getFeatureExtractionFactory()
            .createFeatureExtraction()
            .createFeatureSet(sample, DPFPDataPurpose.DATA_PURPOSE_VERIFICATION);
        var verification = DPFPGlobal.getVerificationFactory().createVerification();
        boolean findAll = FIND_ALL.matcher(body).find();
        List<String> matches = new ArrayList<>();
        int evaluated = 0;
        int invalid = 0;

        Matcher candidates = CANDIDATE.matcher(body);
        while (candidates.find()) {
            try {
                DPFPTemplate template = DPFPGlobal.getTemplateFactory()
                    .createTemplate(decode(candidates.group(2).replace("\\/", "/")));
                evaluated++;
                if (verification.verify(features, template).isVerified()) {
                    matches.add("\"" + escape(candidates.group(1)) + "\"");
                    if (!findAll) {
                        break;
                    }
                }
            } catch (RuntimeException exception) {
                invalid++;
            }
        }

        return "{\"coincidencias\":[" + String.join(",", matches) + "],\"evaluadas\":" + evaluated
            + ",\"invalidas\":" + invalid + "}";
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

    private static void handleImpresion(HttpExchange exchange) throws IOException {
        if (!"POST".equals(exchange.getRequestMethod())) {
            respond(exchange, 405, "{\"error\":\"Use POST para enviar un recibo.\"}");
            return;
        }
        if (exchange.getRequestHeaders().containsKey("Origin")) {
            respond(exchange, 403, "{\"error\":\"La impresión solo puede solicitarse desde el servicio local.\"}");
            return;
        }

        try {
            byte[] content = exchange.getRequestBody().readNBytes(MAX_PRINT_REQUEST_BYTES + 1);
            if (content.length > MAX_PRINT_REQUEST_BYTES) {
                respond(exchange, 413, "{\"error\":\"El contenido del recibo excede el límite permitido.\"}");
                return;
            }

            String body = new String(content, StandardCharsets.UTF_8);
            respond(exchange, 200, imprimir(body));
        } catch (Exception | LinkageError exception) {
            String message = exception.getMessage() == null ? "No se pudo imprimir el recibo." : exception.getMessage();
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
        StringBuilder escaped = new StringBuilder();
        for (char character : value.toCharArray()) {
            switch (character) {
                case '\\' -> escaped.append("\\\\");
                case '"' -> escaped.append("\\\"");
                case '\b' -> escaped.append("\\b");
                case '\f' -> escaped.append("\\f");
                case '\n' -> escaped.append("\\n");
                case '\r' -> escaped.append("\\r");
                case '\t' -> escaped.append("\\t");
                default -> {
                    if (character < 0x20) {
                        escaped.append(String.format("\\u%04x", (int) character));
                    } else {
                        escaped.append(character);
                    }
                }
            }
        }
        return escaped.toString();
    }

    @FunctionalInterface
    private interface Handler {
        String handle(String body) throws Exception;
    }
}
