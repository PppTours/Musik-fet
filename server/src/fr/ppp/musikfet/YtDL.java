package fr.ppp.musikfet;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.io.InputStream;
import java.lang.ProcessBuilder.Redirect;
import java.net.URL;
import java.nio.file.Files;
import java.util.ArrayList;
import java.util.List;

public class YtDL {
	
	private static final File WORKDING_DIR = new File("music");
	private static final File EXEC_FILE = new File(WORKDING_DIR, "yt-dlp.exe");
	
	private static final String DOWNLOAD_URL = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe";
	
	public static void downloadIfNotExists() throws IOException {
		if(!WORKDING_DIR.exists())
			WORKDING_DIR.mkdir();
		if(EXEC_FILE.exists())
			return;
		System.out.println("Downloading yt-dlp from " + DOWNLOAD_URL);
		try (InputStream is = new URL(DOWNLOAD_URL).openStream()){
			Files.copy(is, EXEC_FILE.toPath());
		}
		System.out.println("Finished downloading");
	}
	
	public static List<VideoInfo> searchForVideos(String searchQuery, int searchCount) throws IOException {
		List<VideoInfo> videos = new ArrayList<>();
		
		// sanitize the search query before using it as a command part
		// TODO a more lenient sanitizer could be to only remove " and \
		searchQuery = searchQuery.replaceAll("[^a-zA-Z _,@0-9#-]", "");
		
		// execute yt-dl
		String[] commandParts = {
				EXEC_FILE.getAbsolutePath(),
				String.format("ytsearch%d:%s", searchCount, searchQuery),
				"-O", "\"%(id)s:%(title)s\""
		};
		String stdout = execCommand(commandParts);
		
		// parse the results
		for(String line : stdout.split("\n")) {
			int split = line.indexOf(':');
			String videoId = line.substring(0, split);
			String videoTitle = line.substring(split+1);
			videos.add(new VideoInfo(videoId, videoTitle));
		}
		
		return videos;
	}
	
	private static String execCommand(String[] args) throws IOException {
		ProcessBuilder pb = new ProcessBuilder(args)
				.directory(EXEC_FILE.getParentFile())
				.redirectError(Redirect.INHERIT);
		
		
		System.out.println("Running yt-dl with as '" + String.join(" ", args) + "'"); // TODO use ansi colors/loggers!
		Process process = pb.start();
		
		InputStream stdoutStream = process.getInputStream();
		ByteArrayOutputStream bufferedStdout = new ByteArrayOutputStream();
		stdoutStream.transferTo(bufferedStdout);
		int status;
		try { status = process.waitFor(); } catch (InterruptedException x) { status = -1; }
		if(status != 0)
			throw new IOException("yl-dlp exited with error code " + status);
		String stdout = new String(bufferedStdout.toByteArray());
		System.out.println("Finished executing yl-dl");
		
		return stdout;
	}

	public static final class VideoInfo {
		
		public final String id;
		public final String title;
		
		public VideoInfo(String id, String title) {
			this.id = id;
			this.title = title;
		}
		
		@Override
		public String toString() {
			return String.format("Video(id=%s title='%s')", id, title);
		}
		
	}
}
